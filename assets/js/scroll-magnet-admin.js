(function () {
  const preview = document.getElementById("scroll-magnet-preview");

  if (!preview) {
    return;
  }

  function getFieldValue(selector, fallback = "") {
    const field = document.querySelector(selector);

    if (!field) {
      return fallback;
    }

    if (field.type === "checkbox") {
      return field.checked;
    }

    return field.value || fallback;
  }

  function getStage(index) {
    return {
      enabled: Boolean(
        getFieldValue(`input[name="scroll_magnet_settings[stage_${index}][enabled]"]`)
      ),
      percent: getFieldValue(
        `input[name="scroll_magnet_settings[stage_${index}][percent]"]`,
        ""
      ),
      type: getFieldValue(
        `select[name="scroll_magnet_settings[stage_${index}][type]"]`,
        "banner"
      ),
      message: getFieldValue(
        `input[name="scroll_magnet_settings[stage_${index}][message]"]`,
        ""
      ),
      buttonLabel: getFieldValue(
        `input[name="scroll_magnet_settings[stage_${index}][button_label]"]`,
        ""
      ),
    };
  }

  function stageTypeLabel(type) {
    if (type === "floating_button") {
      return "Bouton flottant";
    }

    if (type === "popup") {
      return "Popup douce";
    }

    return "Bandeau bas";
  }

  function renderPreview() {
    const stages = [1, 2, 3].map(getStage);
    const enabledStages = stages.filter((stage) => stage.enabled);

    if (!enabledStages.length) {
      preview.innerHTML =
        '<div class="scroll-magnet-admin__preview-empty">Aucun trigger actif pour le moment.</div>';
      return;
    }

    preview.innerHTML = enabledStages
      .map(
        (stage, index) => `
          <article class="scroll-magnet-admin__preview-card">
            <div class="scroll-magnet-admin__preview-top">
              <span class="scroll-magnet-admin__preview-badge">Trigger ${index + 1}</span>
              <span class="scroll-magnet-admin__preview-meta">${stage.percent}% • ${stageTypeLabel(stage.type)}</span>
            </div>
            <p class="scroll-magnet-admin__preview-message">${stage.message || "Ajoute un message ici."}</p>
            <span class="scroll-magnet-admin__preview-button">${stage.buttonLabel || "Texte du bouton"}</span>
          </article>
        `
      )
      .join("");
  }

  document.addEventListener("input", renderPreview);
  document.addEventListener("change", renderPreview);
  renderPreview();
})();
