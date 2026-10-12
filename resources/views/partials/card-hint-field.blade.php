{{--
  Optional hint of a card ("(Zug)", "ugs."), folded away unless it has a
  value. It is shown before flipping, so the help text warns against
  putting the answer in.

  @param string      $id
  @param string|null $value
--}}
<details class="field" @if ($value !== null && $value !== '') open @endif>
  <summary class="btn btn-ghost btn-sm self-start cursor-pointer list-none">+ Hinweis</summary>
  <div class="field mt-2">
    <label class="label" for="{{ $id }}">Hinweis <span class="font-normal text-muted">(freiwillig)</span></label>
    <input class="input" type="text" id="{{ $id }}" name="hint" value="{{ $value }}"
           maxlength="{{ config('learning.max_hint_length') }}" autocomplete="off" placeholder="z. B. (Zug) oder ugs.">
    <p class="hint">Ist beim Lernen schon vor dem Umdrehen zu sehen – nicht die Lösung hineinschreiben.</p>
  </div>
</details>
