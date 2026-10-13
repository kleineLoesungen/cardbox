{{--
  Read aloud with the Web Speech API, for the learning round and the
  dictionary. Buttons carry data-speak and are only shown when the device
  has a voice for the language; see sideToSpeak() for where the text comes
  from. Include once per page, after the buttons' container.
--}}
<script>
(() => {
  // --- Read aloud (Web Speech API) ---------------------------------------
  // Only offered when the device has a voice for the language; voices often
  // arrive a moment after page load ("voiceschanged").
  const voiceFor = (lang) => {
    if (!lang || !("speechSynthesis" in window)) return null;
    const voices = speechSynthesis.getVoices();
    return voices.find((v) => v.lang.toLowerCase().startsWith(lang.toLowerCase())) ?? null;
  };
  // A button either names its text (data-speak-text, data-speak-lang) or
  // belongs to a flash card: the question before flipping, the answer after.
  const sideToSpeak = (button) => {
    if (button.dataset.speakText !== undefined) {
      return { text: button.dataset.speakText, lang: button.dataset.speakLang };
    }
    const flipped = button.closest("[data-card-screen]")?.classList.contains("is-flipped");
    return flipped
      ? { text: button.dataset.answer, lang: button.dataset.answerLang }
      : { text: button.dataset.question, lang: button.dataset.questionLang };
  };
  const updateSpeakButtons = () => {
    document.querySelectorAll("[data-speak]").forEach((button) => {
      const { lang } = sideToSpeak(button);
      const available = !!voiceFor(lang);
      button.hidden = !available;
      // A language without a voice (and a browser that can speak at all):
      // point to the help instead of silently hiding the button.
      const missing = button.parentElement.querySelector("[data-speak-missing]");
      if (missing) missing.hidden = available || !lang || !("speechSynthesis" in window);
    });
  };
  if ("speechSynthesis" in window) {
    speechSynthesis.addEventListener?.("voiceschanged", updateSpeakButtons);
  }
  document.body.addEventListener("htmx:afterSettle", updateSpeakButtons);
  document.addEventListener("cardflipped", updateSpeakButtons);
  updateSpeakButtons();

  document.addEventListener("click", (e) => {
    const button = e.target.closest("[data-speak]");
    if (!button) return;
    const { text, lang } = sideToSpeak(button);
    const voice = voiceFor(lang);
    if (!voice || !text) return;
    speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.voice = voice;
    utterance.lang = voice.lang;
    utterance.rate = 0.9;
    speechSynthesis.speak(utterance);
  });

})();
</script>
