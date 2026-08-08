const header = document.querySelector("[data-header]");
const revealItems = document.querySelectorAll(".reveal");
const contactForm = document.querySelector(".contact-form");
const formMessage = document.querySelector(".form-message");
const featureToggles = document.querySelectorAll("[data-feature-toggle]");
const galleries = document.querySelectorAll("[data-gallery]");
const backToTop = document.querySelector("[data-back-to-top]");

const updateHeader = () => {
  header?.classList.toggle("is-scrolled", window.scrollY > 12);
  backToTop?.classList.toggle("is-visible", window.scrollY > 420);
};

updateHeader();
window.addEventListener("scroll", updateHeader, { passive: true });

backToTop?.addEventListener("click", () => {
  window.scrollTo({ top: 0, behavior: "smooth" });
});

if ("IntersectionObserver" in window) {
  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.14 }
  );

  revealItems.forEach((item) => revealObserver.observe(item));
} else {
  revealItems.forEach((item) => item.classList.add("is-visible"));
}

contactForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  const formData = new FormData(contactForm);
  const groupName = formData.get("groupName");
  const representativeName = formData.get("representativeName");
  const email = formData.get("email");

  formMessage?.classList.remove("is-error");

  if (!groupName || !representativeName || !email) {
    formMessage.textContent = "団体名、代表者名、メールアドレスを入力してください。";
    return;
  }

  const submitButton = contactForm.querySelector('button[type="submit"]');
  if (submitButton) submitButton.disabled = true;

  try {
    const response = await fetch(contactForm.action, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      const errors = data.errors ? Object.values(data.errors).flat() : [];
      throw new Error(errors[0] || data.message || "送信に失敗しました。時間をおいてもう一度お試しください。");
    }

    formMessage.textContent = data.message || "お問い合わせありがとうございます。担当者よりご連絡します。";
    contactForm.reset();
  } catch (error) {
    formMessage?.classList.add("is-error");
    formMessage.textContent = error.message || "送信に失敗しました。時間をおいてもう一度お試しください。";
  } finally {
    if (submitButton) submitButton.disabled = false;
  }
});

featureToggles.forEach((toggle) => {
  toggle.addEventListener("click", () => {
    const detailId = toggle.getAttribute("aria-controls");
    const detail = detailId ? document.getElementById(detailId) : null;
    if (!detail) return;

    const isOpening = toggle.getAttribute("aria-expanded") !== "true";

    featureToggles.forEach((item) => {
      const itemDetailId = item.getAttribute("aria-controls");
      const itemDetail = itemDetailId ? document.getElementById(itemDetailId) : null;
      item.setAttribute("aria-expanded", "false");
      if (itemDetail) itemDetail.hidden = true;
    });

    toggle.setAttribute("aria-expanded", String(isOpening));
    detail.hidden = !isOpening;

    if (isOpening) {
      detail.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  });
});

galleries.forEach((gallery) => {
  const panels = Array.from(gallery.querySelectorAll("[data-gallery-panel]"));
  if (panels.length === 0) return;

  const previousButton = gallery.querySelector("[data-gallery-prev]");
  const nextButton = gallery.querySelector("[data-gallery-next]");
  let currentIndex = Math.max(0, panels.findIndex((panel) => !panel.hidden));

  const showPanel = (nextIndex) => {
    currentIndex = (nextIndex + panels.length) % panels.length;
    panels.forEach((panel, index) => {
      panel.hidden = index !== currentIndex;
    });
  };

  const showPrevious = () => showPanel(currentIndex - 1);
  const showNext = () => showPanel(currentIndex + 1);

  previousButton?.addEventListener("click", showPrevious);
  nextButton?.addEventListener("click", showNext);
});
