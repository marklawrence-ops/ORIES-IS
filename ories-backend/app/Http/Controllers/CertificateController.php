<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Faculty request certificates for verified records; the director of the record's office
 * issues them. Each issued certificate carries a serial number and a public verification code.
 */
class CertificateController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->validate(['status' => 'nullable|in:requested,issued,declined'])['status'] ?? null;

        $query = Certificate::query()
            ->with(['project:id,title,office,stage', 'recipient:id,name', 'issuer:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest();

        if ($user->hasRole('director', 'staff')) {
            $query->whereHas('project', fn ($q) => $user->office ? $q->where('office', $user->office) : $q);
        } elseif (! $user->hasRole('vp', 'oup')) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'type' => ['required', Rule::in(Certificate::TYPES)],
            'purpose' => ['required', Rule::in(Certificate::PURPOSES)],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $project = Project::with('members', 'outcomes')->findOrFail($data['project_id']);
        abort_unless($project->isInvolved($user), 403, 'You can only request certificates for your own records.');
        abort_unless($project->verification_status === 'verified', 422, 'The record must be verified first.');

        $eligibility = $this->eligibilityError($project, $data['type']);
        abort_if($eligibility !== null, 422, $eligibility);

        $certificate = DB::transaction(function () use ($project, $user, $data) {
            // Locking the record serializes requests so a double click cannot create two
            Project::lockForUpdate()->findOrFail($project->id);

            $duplicate = Certificate::where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->where('type', $data['type'])
                ->whereIn('status', ['requested', 'issued'])
                ->exists();
            abort_if($duplicate, 422, 'You already have this certificate for this record.');

            $certificate = Certificate::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'type' => $data['type'],
                'purpose' => $data['purpose'],
                'request_remarks' => $data['remarks'] ?? null,
                'status' => 'requested',
            ]);

            ActivityLog::record($project, $user, 'certificate_requested', 'Requested a '.$data['type'].' certificate.');

            return $certificate;
        });

        $directors = User::where('role', 'director')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('office')->orWhere('office', $project->office))->get();
        $this->notifier->send($directors, 'Certificate request', $user->name.' — '.$project->title, '/certificates');

        return response()->json(['message' => 'Certificate requested.', 'certificate' => $certificate], 201);
    }

    public function issue(Request $request, Certificate $certificate): JsonResponse
    {
        $user = $request->user();
        $project = $certificate->project;
        $this->authorizeDirector($user, $project);
        abort_unless($certificate->status === 'requested', 422, 'This request has already been decided.');

        $remarks = $request->validate(['remarks' => 'nullable|string|max:1000'])['remarks'] ?? null;

        // Two certificates issued at the same moment can pick the same serial; the unique index
        // rejects the second one, which then retries with the next number
        $certificate = retry(3, fn () => DB::transaction(function () use ($certificate, $user, $project, $remarks) {
            $locked = Certificate::lockForUpdate()->findOrFail($certificate->id);
            abort_unless($locked->status === 'requested', 422, 'This request has already been decided.');

            $prefix = 'ORIES-'.strtoupper(substr($project->office, 0, 3)).'-'.now()->year.'-';
            $last = Certificate::where('serial_no', 'like', $prefix.'%')->orderByDesc('serial_no')->value('serial_no');
            $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

            $locked->update([
                'status' => 'issued',
                'serial_no' => $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'verification_code' => strtoupper(Str::random(12)),
                'issued_by' => $user->id,
                'issued_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            ActivityLog::record($project, $user, 'certificate_issued', 'Issued certificate '.$locked->serial_no.' to '.$locked->recipient->name.'.');

            return $locked;
        }), 100, fn (Throwable $e) => $e instanceof UniqueConstraintViolationException);

        $this->notifier->send($certificate->recipient, 'Certificate issued', $project->title, '/certificates/'.$certificate->id);

        return response()->json(['message' => 'Certificate issued.', 'certificate' => $certificate->fresh()]);
    }

    public function decline(Request $request, Certificate $certificate): JsonResponse
    {
        $user = $request->user();
        $this->authorizeDirector($user, $certificate->project);
        abort_unless($certificate->status === 'requested', 422, 'This request has already been decided.');

        $remarks = $request->validate(['remarks' => 'required|string|max:1000'])['remarks'];

        DB::transaction(function () use ($certificate, $user, $remarks) {
            $locked = Certificate::lockForUpdate()->findOrFail($certificate->id);
            abort_unless($locked->status === 'requested', 422, 'This request has already been decided.');
            $locked->update(['status' => 'declined', 'decision_remarks' => $remarks, 'issued_by' => $user->id]);

            ActivityLog::record($certificate->project, $user, 'certificate_declined', 'Declined a certificate request: '.$remarks);
        });
        $this->notifier->send($certificate->recipient, 'Certificate request declined', $remarks, '/certificates');

        return response()->json(['message' => 'Certificate request declined.']);
    }

    /**
     * Data for the printable certificate (recipient, issuers, or office reviewers).
     */
    public function show(Request $request, Certificate $certificate): JsonResponse
    {
        $user = $request->user();
        $project = $certificate->project;
        $allowed = $certificate->user_id === $user->id
            || $user->hasRole('vp', 'oup')
            || ($user->hasRole('director', 'staff') && ($user->office === null || $user->office === $project->office));
        abort_unless($allowed, 403);

        return response()->json($this->present($certificate));
    }

    /**
     * Public endpoint for anyone checking whether a presented certificate is genuine.
     */
    public function verifyPublic(string $code): JsonResponse
    {
        $certificate = Certificate::where('verification_code', strtoupper($code))->where('status', 'issued')->first();

        if (! $certificate) {
            return response()->json(['valid' => false, 'message' => 'No issued certificate matches this code.'], 404);
        }

        return response()->json(['valid' => true, ...$this->present($certificate)]);
    }

    private function present(Certificate $certificate): array
    {
        $certificate->load(['project.outcomes', 'project.campus:id,name', 'project.unit:id,name', 'recipient:id,name,position', 'issuer:id,name,position,office']);

        return ['certificate' => $certificate];
    }

    private function authorizeDirector(User $user, Project $project): void
    {
        abort_unless(
            $user->hasRole('director') && ($user->office === null || $user->office === $project->office),
            403,
            'Only the director of this office can decide certificate requests.',
        );
    }

    private function eligibilityError(Project $project, string $type): ?string
    {
        return match ($type) {
            'completion' => $project->stage === 'completed' ? null : 'A completion certificate requires a completed record.',
            'publication' => $project->outcomes->contains('type', 'publication') ? null : 'A publication certificate requires a verified publication.',
            'utilization' => $project->outcomes->whereIn('type', ['commercialization', 'policy', 'technology_transfer'])->isNotEmpty()
                ? null
                : 'A utilization certificate requires a verified commercialization, policy, or technology transfer outcome.',
            default => null,
        };
    }
}
