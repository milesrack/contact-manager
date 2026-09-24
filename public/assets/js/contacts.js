import { requestJson } from "./api.js";

const search = document.querySelector("#contact-search");
const list = document.querySelector("[data-contacts]");
const template = document.querySelector("[data-contact-template]");
const status = document.querySelector("[data-status]");
const results = document.querySelector("[data-results]");
const listError = document.querySelector("[data-list-error]");
const loadMore = document.querySelector("[data-load-more]");
const contactDialog = document.querySelector("[data-contact-dialog]");
const contactForm = document.querySelector("[data-contact-form]");
const deleteDialog = document.querySelector("[data-delete-dialog]");
const deleteForm = document.querySelector("[data-delete-form]");
const contacts = new Map();
const limit = 50;
let afterId = null;
let generation = 0;
let debounce;
let loading = false;
let retryAppend = false;
let editingId = null;
let deletingId = null;

// Authentication handling remains in the shared request helper; login failures
// on the public authentication pages must still display their inline errors.
function request(url, options = {}) {
  return requestJson(url, { ...options, redirectOnUnauthorized: true });
}

function renderContact(contact) {
  const row = template.content.firstElementChild.cloneNode(true);
  const name = `${contact.first_name} ${contact.last_name}`;
  row.querySelector("[data-name]").textContent = name;
  row.querySelector("[data-company]").textContent = contact.company || "—";
  row.querySelector("[data-email]").textContent = contact.email || "—";
  row.querySelector("[data-phone]").textContent = contact.phone_number;
  for (const action of ["edit", "delete"]) {
    const button = row.querySelector(`[data-${action}]`);
    button.dataset.contactId = contact.contact_id;
    button.setAttribute(
      "aria-label",
      `${action === "edit" ? "Edit" : "Delete"} ${name}`,
    );
  }
  contacts.set(String(contact.contact_id), contact);
  list.append(row);
}

async function loadContacts(append = false) {
  if (append && loading) return;
  clearTimeout(debounce);
  const currentGeneration = ++generation;
  const query = search.value;
  const params = new URLSearchParams({ query, limit });
  if (append && afterId !== null) params.set("after_id", afterId);
  loading = true;
  if (!append) {
    afterId = null;
    loadMore.hidden = true;
  }
  loadMore.disabled = true;
  results.setAttribute("aria-busy", "true");
  status.textContent = append
    ? "Loading more contacts..."
    : "Loading contacts...";
  listError.hidden = true;

  try {
    const data = await request(`/api/contacts?${params}`);
    // A newer query or mutation supersedes this response, even during debounce.
    if (currentGeneration !== generation) return;
    const focus = document.activeElement;
    const focusedId = focus?.dataset.contactId;
    const focusedAction = focus?.hasAttribute("data-edit") ? "edit" : "delete";
    const restoreFocus =
      list.contains(focus) ||
      focus === loadMore ||
      !!focus?.closest("[data-empty]");
    if (!append) {
      contacts.clear();
      list.replaceChildren();
    }
    data.contacts.forEach(renderContact);
    afterId = data.contacts.at(-1)?.contact_id ?? afterId;
    loadMore.hidden = data.contacts.length < limit;
    loadMore.disabled = false;
    document.querySelector("[data-contact-list]").hidden = contacts.size === 0;
    document.querySelector("[data-empty]").hidden =
      contacts.size !== 0 || query !== "";
    document.querySelector("[data-no-results]").hidden =
      contacts.size !== 0 || query === "";
    document.querySelector("[data-no-results-message]").textContent =
      `No contacts found for "${query}".`;
    status.textContent = `${contacts.size} ${contacts.size === 1 ? "contact" : "contacts"} shown${query ? ` for "${query}"` : ""}.`;
    if (restoreFocus) {
      const replacement = [
        ...list.querySelectorAll(`[data-${focusedAction}]`),
      ].find((button) => button.dataset.contactId === focusedId);
      (replacement || (!loadMore.hidden ? loadMore : search)).focus();
    }
  } catch (failure) {
    if (currentGeneration !== generation) return;
    retryAppend = append;
    listError.hidden = false;
    document.querySelector("[data-list-error-message]").textContent =
      failure.message;
    status.textContent = contacts.size
      ? "Could not refresh. Previously loaded contacts are still shown."
      : "Could not load contacts.";
  } finally {
    if (currentGeneration === generation) {
      loading = false;
      loadMore.disabled = false;
      results.setAttribute("aria-busy", "false");
      document.querySelector("[data-skeleton]").hidden = true;
    }
  }
}

search.addEventListener("input", () => {
  clearTimeout(debounce);
  ++generation;
  afterId = null;
  loading = true;
  loadMore.hidden = true;
  results.setAttribute("aria-busy", "true");
  status.textContent = "Waiting to search...";
  debounce = setTimeout(() => loadContacts(), 300);
});
loadMore.addEventListener("click", () => loadContacts(true));
document
  .querySelector("[data-retry]")
  .addEventListener("click", () => loadContacts(retryAppend));

function openContact(contact = null) {
  editingId = contact?.contact_id ?? null;
  contactForm.reset();
  contactForm.querySelector("[data-form-error]").textContent = "";
  document.querySelector("#contact-form-title").textContent = contact
    ? "Edit contact"
    : "Add contact";
  for (const input of contactForm.querySelectorAll("input")) {
    input.value = contact?.[input.name] ?? "";
    input.setCustomValidity("");
  }
  contactDialog.showModal();
  contactForm.elements.first_name.focus();
}

document.querySelectorAll("[data-add]").forEach((button) => {
  button.addEventListener("click", () => openContact());
});
list.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-contact-id]");
  if (!button) return;
  const contact = contacts.get(button.dataset.contactId);
  if (button.hasAttribute("data-edit")) {
    openContact(contact);
  } else {
    deletingId = contact.contact_id;
    deleteForm.querySelector("[data-form-error]").textContent = "";
    deleteForm.querySelector("[data-delete-name]").textContent =
      `${contact.first_name} ${contact.last_name}`;
    deleteDialog.showModal();
  }
});

// Native dialogs provide modal focus containment and restore their opener.
// Keep them open (including on Escape) while a mutation is in flight.
for (const dialog of [contactDialog, deleteDialog]) {
  dialog.querySelectorAll("[data-close]").forEach((button) => {
    button.addEventListener("click", () => dialog.close());
  });
  dialog.addEventListener("cancel", (event) => {
    if (dialog.getAttribute("aria-busy") === "true") event.preventDefault();
  });
}

for (const input of contactForm.querySelectorAll("input")) {
  input.addEventListener("input", () => input.setCustomValidity(""));
}

function contactData() {
  const data = {};
  for (const input of contactForm.querySelectorAll("input")) {
    const value = input.value.trim();
    const bytes = new TextEncoder().encode(value).length;
    input.setCustomValidity(
      input.required && !value
        ? "Enter a value, not just spaces."
        : bytes > input.maxLength
          ? `Use no more than ${input.maxLength} bytes (some characters use more than one).`
          : "",
    );
    data[input.name] = value || null;
  }
  return contactForm.reportValidity() ? data : null;
}

async function mutate(dialog, url, method, data) {
  if (dialog.getAttribute("aria-busy") === "true") return;
  const controls = dialog.querySelectorAll("button, input");
  const submit = dialog.querySelector('[type="submit"]');
  const label = submit.textContent;
  const error = dialog.querySelector("[data-form-error]");
  dialog.setAttribute("aria-busy", "true");
  controls.forEach((control) => (control.disabled = true));
  submit.textContent = method === "DELETE" ? "Deleting..." : "Saving...";
  error.textContent = "";
  try {
    await request(url, {
      method,
      ...(data
        ? {
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
          }
        : {}),
    });
    dialog.close();
    afterId = null;
    loadContacts();
  } catch (failure) {
    error.textContent = failure.message;
  } finally {
    dialog.setAttribute("aria-busy", "false");
    controls.forEach((control) => (control.disabled = false));
    submit.textContent = label;
    if (dialog.open) submit.focus();
  }
}
contactForm.addEventListener("submit", (event) => {
  event.preventDefault();
  if (contactDialog.getAttribute("aria-busy") === "true") return;
  const data = contactData();
  if (data)
    mutate(
      contactDialog,
      editingId === null ? "/api/contacts" : `/api/contacts/${editingId}`,
      editingId === null ? "POST" : "PATCH",
      data,
    );
});
deleteForm.addEventListener("submit", (event) => {
  event.preventDefault();
  mutate(deleteDialog, `/api/contacts/${deletingId}`, "DELETE");
});

const logout = document.querySelector("[data-logout]");
logout.addEventListener("click", async () => {
  if (logout.disabled) return;
  logout.disabled = true;
  logout.textContent = "Logging out...";
  try {
    await request("/api/auth/logout", { method: "POST" });
    window.location.assign("/login");
  } catch (failure) {
    document.querySelector("[data-logout-error]").textContent = failure.message;
    logout.disabled = false;
    logout.textContent = "Log out";
  }
});

loadContacts();
