<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">

        {{ $slot }}

        <x-toast position="top-right" timeout="3500" />
        
        @fluxScripts
    </body>
</html>