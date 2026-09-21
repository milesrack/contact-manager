import { requestJson } from "./api.js";

const form = document.querySelector("[data-auth-form]");
const button = form.querySelector('button[type="submit"]');
const error = form.querySelector("[data-form-error]");
const label = button.textContent;

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (button.disabled) return;

  const fields = new FormData(form);
  button.disabled = true;
  button.textContent = form.dataset.pending;
  error.textContent = "";

  try {
    await requestJson(form.action, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email: fields.get("email"),
        password: fields.get("password"),
      }),
    });
    window.location.assign(form.dataset.redirect);
  } catch (failure) {
    error.textContent = failure.message;
    button.disabled = false;
    button.textContent = label;
  }
});
