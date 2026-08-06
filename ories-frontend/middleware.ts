import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
    const token = request.cookies.get('ories_token')?.value;
    const role = request.cookies.get('ories_role')?.value;
    const { pathname } = request.nextUrl;

    // Helper to translate 'vp_ories' database role to the '/vp' route prefix
    const routePrefix = role === 'vp_ories' ? 'vp' : role;

    // 1. If the user is not logged in and tries to access a protected route, redirect to login
    if (!token && (pathname.startsWith('/vp') || pathname.startsWith('/director') || pathname.startsWith('/head'))) {
        return NextResponse.redirect(new URL('/', request.url));
    }

    // 2. If the user is logged in, restrict them to their specific role's route group
    if (token && role) {
        // Prevent Directors and Heads from accessing VP routes
        if (pathname.startsWith('/vp') && role !== 'vp_ories') {
            return NextResponse.redirect(new URL(`/${routePrefix}/dashboard`, request.url));
        }
        
        // Prevent VPs and Heads from accessing Director routes
        if (pathname.startsWith('/director') && role !== 'director') {
            return NextResponse.redirect(new URL(`/${routePrefix}/dashboard`, request.url));
        }

        // Prevent VPs and Directors from accessing Head routes
        if (pathname.startsWith('/head') && role !== 'head') {
            return NextResponse.redirect(new URL(`/${routePrefix}/dashboard`, request.url));
        }

        // If a logged-in user tries to visit the login page, bounce them to their correct dashboard
        if (pathname === '/') {
            return NextResponse.redirect(new URL(`/${routePrefix}/dashboard`, request.url));
        }
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/', '/vp/:path*', '/director/:path*', '/head/:path*'],
};