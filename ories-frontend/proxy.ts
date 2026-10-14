import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

// Optimistic check only; the Laravel API enforces every role permission
export function proxy(request: NextRequest) {
    const token = request.cookies.get('ories_token')?.value;
    const { pathname } = request.nextUrl;
    const isPublic = pathname === '/' || pathname === '/register' || pathname.startsWith('/verify');

    if (!token && !isPublic) {
        return NextResponse.redirect(new URL('/', request.url));
    }

    if (token && (pathname === '/' || pathname === '/register')) {
        return NextResponse.redirect(new URL('/dashboard', request.url));
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:png|jpg|svg|ico)$).*)'],
};
