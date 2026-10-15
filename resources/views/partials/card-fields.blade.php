{{--
  Fields of the edit form: meanings of both sides (the first is the main
  meaning, ☆ moves another one up, see layouts/app) and the hint. Shared by
  the edit sheet in a deck and the one in the dictionary.

  @param \App\Models\Deck $deck
  @param \App\Models\Card $card
  @param string $idPrefix  keeps element ids unique per page, e.g. "edit"
--}}
@php
    $frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag();
    $backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag();
@endphp
<div class="field">
  <span class="label">{{ $deck->frontLabel() }}</span>
  <div class="stack gap-2" id="{{ $idPrefix }}-front-group">
    @foreach ($card->front as $i => $meaning)
      @include('partials.meaning-row', ['name' => 'front[]', 'value' => $meaning, 'lang' => $frontTag, 'label' => $deck->frontLabel(), 'main' => $i === 0])
    @endforeach
  </div>
  <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="{{ $idPrefix }}-front-group">+ weitere Bedeutung</button>
</div>

<div class="field">
  <span class="label">{{ $deck->backLabel() }}</span>
  <div class="stack gap-2" id="{{ $idPrefix }}-back-group">
    @foreach ($card->back as $i => $meaning)
      @include('partials.meaning-row', ['name' => 'back[]', 'value' => $meaning, 'lang' => $backTag, 'label' => $deck->backLabel(), 'main' => $i === 0])
    @endforeach
  </div>
  <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="{{ $idPrefix }}-back-group">+ weitere Bedeutung</button>
  <p class="hint">Die oberste Bedeutung ist die Hauptbedeutung. ☆ rückt eine andere nach oben. Ein Feld leeren, um eine Bedeutung zu entfernen.</p>
</div>

@include('partials.card-hint-field', ['id' => $idPrefix.'-hint', 'value' => $card->hint])
