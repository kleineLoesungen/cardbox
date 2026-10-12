{{--
  One meaning in the edit form: the input and ☆ "make main meaning". The
  first row of a group is the main meaning; its button is hidden. The script
  in layouts/app moves rows and keeps the buttons and labels in step.

  @param string      $name   front[] or back[]
  @param string      $value
  @param string|null $lang
  @param string      $label  side label for screen readers
  @param bool        $main
--}}
<div class="flex min-w-0 items-center gap-2" data-meaning-row>
  <input class="input input-lg min-w-0 flex-1" type="text" name="{{ $name }}" value="{{ $value }}" lang="{{ $lang }}"
         aria-label="{{ $label }}" data-label="{{ $label }}" autocomplete="off">
  <button type="button" class="btn btn-ghost btn-icon shrink-0" data-make-main
          aria-label="Als Hauptbedeutung" title="Als Hauptbedeutung" @if ($main) hidden @endif>☆</button>
</div>
