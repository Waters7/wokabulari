@props(['title' => null])
<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        @include('partials.head')
    </head>
    <body class="h-full bg-hutan text-santan antialiased">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
