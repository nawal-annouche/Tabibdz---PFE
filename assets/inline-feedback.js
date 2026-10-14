export function setFeedback({
  container,
  textNode,
  message,
  type = "error",
  baseClass = "alert",
  visibleClass = "show",
  scroll = false,
}) {
  if (!container) return;
  if (textNode) textNode.textContent = message || "";
  container.className = `${baseClass} ${type}${message ? ` ${visibleClass}` : ""}`.trim();
  if (message && scroll) {
    container.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }
}

export function clearFeedback({
  container,
  textNode,
  baseClass = "alert",
  idleType = "error",
}) {
  if (!container) return;
  if (textNode) textNode.textContent = "";
  container.className = `${baseClass} ${idleType}`.trim();
}

export function setButtonLoading({
  button,
  loading,
  loadingClass = "loading",
  idleLabel,
  loadingLabel,
}) {
  if (!button) return;
  button.disabled = loading;
  button.classList.toggle(loadingClass, loading);
  if (typeof idleLabel === "string" && typeof loadingLabel === "string") {
    button.textContent = loading ? loadingLabel : idleLabel;
  }
}
