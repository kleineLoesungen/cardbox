{{-- Answer to "Lernen" in the dictionary: the button becomes a confirmation. --}}
<p class="flex min-h-12 flex-wrap items-center gap-2 text-sm">
  <span class="badge badge-success">lerne ich</span>
  <a href="/decks/{{ $deck->id }}" class="truncate py-3.5 text-brand hover:underline">in „{{ $deck->name }}“</a>
</p>
