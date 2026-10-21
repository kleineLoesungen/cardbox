{{-- Answer to "In Stapel übernehmen" in the dictionary: the button becomes a confirmation. --}}
<p class="flex min-h-12 items-center gap-2 px-1 text-sm">
  <span class="badge badge-success">übernommen</span>
  <a href="/decks/{{ $deck->id }}" class="truncate py-3.5 text-brand hover:underline">in „{{ $deck->name }}“</a>
</p>
