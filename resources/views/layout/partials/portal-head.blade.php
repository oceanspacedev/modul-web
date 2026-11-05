{{-- Shared <head> assets for standalone pages (participant portal, error pages) that don't use the admin layout. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('template') }}/plugins/fontawesome-free/css/all.min.css">
@vite('resources/css/app.css')
@include('layout.partials.favicon')
