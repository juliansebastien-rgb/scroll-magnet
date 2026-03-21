(function () {
  if (typeof window.ScrollMagnetConfig !== "object" || !Array.isArray(window.ScrollMagnetConfig.stages)) {
    return;
  }

  const config = window.ScrollMagnetConfig;
  const stages = config.stages.map((stage) => ({ ...stage, shown: false }));

  if (!stages.length) {
    return;
  }

  const root = document.createElement("div");
  root.className = "scroll-magnet-root";
  document.body.appendChild(root);
  const startedAt = Date.now();

  function track(stageKey, eventName) {
    if (!config.ajaxUrl || !config.ajaxNonce || !stageKey || !eventName) {
      return;
    }

    const payload = new URLSearchParams();
    payload.set("action", "scroll_magnet_track");
    payload.set("nonce", config.ajaxNonce);
    payload.set("stage", stageKey);
    payload.set("event", eventName);
    payload.set("page", window.location.href);

    fetch(config.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: payload.toString(),
      credentials: "same-origin",
      keepalive: true,
    }).catch(() => {});
  }

  function getScrollPercent() {
    const scrollTop = window.scrollY || window.pageYOffset || 0;
    const viewport = window.innerHeight || document.documentElement.clientHeight || 0;
    const documentHeight = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight
    );
    const maxScrollable = Math.max(documentHeight - viewport, 1);

    return Math.min(100, Math.round((scrollTop / maxScrollable) * 100));
  }

  function createButton(stage) {
    const link = document.createElement("a");
    link.className = "scroll-magnet-button";
    link.href = stage.buttonUrl || "#";
    link.textContent = stage.buttonLabel || "Voir l'offre";
    link.rel = "noopener";
    link.addEventListener("click", () => {
      track(stage.trackKey, "click");
    });
    return link;
  }

  function createHeadline(stage) {
    if (!stage.headline) {
      return null;
    }

    const heading = document.createElement("strong");
    heading.className = "scroll-magnet-headline";
    heading.textContent = stage.headline;
    return heading;
  }

  function closeElement(element) {
    element.classList.add("is-closing");
    window.setTimeout(() => {
      element.remove();
    }, 220);
  }

  function createCloseButton(container) {
    const close = document.createElement("button");
    close.className = "scroll-magnet-close";
    close.type = "button";
    close.setAttribute("aria-label", config.labels?.close || "Close");
    close.textContent = "×";
    close.addEventListener("click", () => closeElement(container));
    return close;
  }

  function renderBanner(stage) {
    const banner = document.createElement("div");
    banner.className = "scroll-magnet-item scroll-magnet-banner";

    const text = document.createElement("p");
    text.className = "scroll-magnet-message";
    text.textContent = stage.message;

    const headline = createHeadline(stage);
    if (headline) {
      banner.appendChild(headline);
    }
    banner.appendChild(text);
    banner.appendChild(createButton(stage));
    banner.appendChild(createCloseButton(banner));
    root.appendChild(banner);
  }

  function renderFloatingButton(stage) {
    const buttonWrap = document.createElement("div");
    buttonWrap.className = "scroll-magnet-item scroll-magnet-floating";

    const caption = document.createElement("span");
    caption.className = "scroll-magnet-floating-caption";
    caption.textContent = stage.message;

    const headline = createHeadline(stage);
    if (headline) {
      buttonWrap.appendChild(headline);
    }
    buttonWrap.appendChild(caption);
    buttonWrap.appendChild(createButton(stage));
    buttonWrap.appendChild(createCloseButton(buttonWrap));
    root.appendChild(buttonWrap);
  }

  function renderPopup(stage) {
    const overlay = document.createElement("div");
    overlay.className = "scroll-magnet-overlay";

    const popup = document.createElement("div");
    popup.className = "scroll-magnet-item scroll-magnet-popup";

    const text = document.createElement("p");
    text.className = "scroll-magnet-message";
    text.textContent = stage.message;

    popup.appendChild(createCloseButton(overlay));
    const headline = createHeadline(stage);
    if (headline) {
      popup.appendChild(headline);
    }
    popup.appendChild(text);
    popup.appendChild(createButton(stage));
    overlay.appendChild(popup);

    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        closeElement(overlay);
      }
    });

    root.appendChild(overlay);
  }

  function showStage(stage) {
    track(stage.trackKey, "view");
    root.setAttribute("data-theme", stage.theme || "classic");

    if (stage.type === "popup") {
      renderPopup(stage);
      return;
    }

    if (stage.type === "floating_button") {
      renderFloatingButton(stage);
      return;
    }

    renderBanner(stage);
  }

  function handleScroll() {
    const percent = getScrollPercent();

    stages.forEach((stage) => {
      if (!stage.shown && percent >= stage.percent) {
        const minSeconds = Number(stage.minSeconds || 0);
        const elapsedSeconds = (Date.now() - startedAt) / 1000;

        if (elapsedSeconds < minSeconds) {
          return;
        }

        stage.shown = true;
        showStage(stage);
      }
    });
  }

  window.addEventListener("scroll", handleScroll, { passive: true });
  window.addEventListener("load", handleScroll);
})();
