@props(['title', 'description' => null])

<div class="flex w-full flex-col text-center">
    <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">{{ $description }}</p>
    @endif
</div>
