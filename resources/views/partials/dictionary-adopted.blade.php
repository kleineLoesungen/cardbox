{{-- Answer to "In Stapel übernehmen" in the dictionary: the button becomes a confirmation. --}}
<p class="flex min-h-11 items-center gap-2 px-1 text-sm">
  <span class="badge badge-success">übernommen</span>
  <a href="/decks/{{ $deck->id }}" class="truncate text-brand hover:underline">in „{{ $deck->name }}“</a>
</p>
