import { requestJson } from "./api.js";

const form = document.querySelector("[data-auth-form]");
const button = form.querySelector('button[type="submit"]');
const error = form.querySelector("[data-form-error]");
const label = button.textContent;
const email = form.elements.namedItem("email");
const password = form.elements.namedItem("password");
const toggle = form.querySelector("[data-password-toggle]");
const registration = form.dataset.registration === "true";

toggle.hidden = false;
toggle.addEventListener("click", () => {
  const show = password.type === "password";
  password.type = show ? "text" : "password";
  toggle.textContent = show ? "Hide" : "Show";
  toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
});

function clearError(input) {
  input.removeAttribute("aria-invalid");
  document.getElementById(`${input.id}-error`).textContent = "";
}

function validate(input) {
  clearError(input);
  let message = "";
  if (input === email) {
    input.value = input.value.trim();
    if (!input.value || input.validity.typeMismatch) {
      message = "Enter a valid email address.";
    } else if (new TextEncoder().encode(input.value).length > 255) {
      message = "Email must be no more than 255 bytes.";
    }
  } else if (!input.value.trim() || input.value.includes("\0")) {
    message = "Enter a valid password.";
  } else if (
    registration &&
    [...input.value].length < Number(input.dataset.minLength)
  ) {
    message = `Use at least ${input.dataset.minLength} characters.`;
  } else if (new TextEncoder().encode(input.value).length > 72) {
    message = "Use no more than 72 bytes; some characters use more than one.";
  }
  if (message) input.setAttribute("aria-invalid", "true");
  document.getElementById(`${input.id}-error`).textContent = message;
  return !message;
}

for (const input of [email, password]) {
  input.addEventListener("input", () => clearError(input));
  input.addEventListener("blur", () => validate(input));
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (button.disabled) return;

  const invalid = [email, password].filter((input) => !validate(input));
  if (invalid.length) {
    invalid[0].focus();
    return;
  }

  button.disabled = true;
  button.textContent = form.dataset.pending;
  error.textContent = "";

  try {
    await requestJson(form.action, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email: email.value,
        password: password.value,
      }),
    });
    window.location.assign(form.dataset.redirect);
  } catch (failure) {
    error.textContent = failure.message;
    button.disabled = false;
    button.textContent = label;
  }
});
