import { requestJson } from "./api.js";

const contactsTbody = document.querySelector("[data-contacts-tbody]");
const contactTemplate = document.querySelector("[data-contact-template]");
let progress = {
  searching: false,
  creating: false,
  updating: false,
  deleting: false,
}

// -- Helper functions for modifying contacts in the DOM -----------------------

// Get contact element from contact ID
function getContactEl(contactId) {
  return contactsTbody.querySelector(`[data-contact-id="${contactId}"]`);
}

// Ensure contact element is in sorted position
function sortContactEl(contactId) {
  const contacts = Array.from(contactsTbody.children);
  const insertContact = getContactEl(contactId);

  const nextContact = contacts.find(contact => {
    const lastNameComparison = contact.dataset.lastName.localeCompare(insertContact.dataset.lastName);

    if (lastNameComparison !== 0) {
      return lastNameComparison > 0;
    }

    return Number(contact.dataset.contactId) > Number(insertContact.dataset.contactId);
  });

  contactsTbody.insertBefore(insertContact, nextContact || null);
}

// Create and set up contact element given data
function createContactEl(data) {
  const newContactFragment = contactTemplate.content.cloneNode(true);
  const newContact = newContactFragment.firstElementChild;

  const editButton = newContact.querySelector("[data-edit-button]");
  const deleteButton = newContact.querySelector("[data-delete-button]");
  const expandCollapseButton = newContact.querySelector("[data-expand-collapse-button]");

  // Setup events
  editButton.addEventListener("click", () => {
    handleUpdatePaneOpen(data.contact_id);
  });

  deleteButton.addEventListener("click", async () => {
    await handleDelete(data.contact_id);
  });

  const collapsed = newContact.querySelector("[data-collapsed]");
  const expanded = newContact.querySelector("[data-expanded]");
  expandCollapseButton.addEventListener("click", () => {
    if (expanded.hidden) {
      expandCollapseButton.innerText = "^";
      expanded.hidden = false;
      collapsed.hidden = true;
    } else {
      expandCollapseButton.innerText = "v";
      expanded.hidden = true;
      collapsed.hidden = false;
    }
  });

  // Set data to be used for reading
  newContact.dataset.contactId = data.contact_id;
  newContact.dataset.firstName = data.first_name;
  newContact.dataset.lastName = data.last_name;
  newContact.dataset.phoneNumber = data.phone_number;
  newContact.dataset.company = data.company ?? "";
  newContact.dataset.email = data.email ?? "";

  // Fill contact data fields and insert to sorted position
  contactsTbody.appendChild(newContact);
  updateContactEl(data.contact_id);
}

// Create contact elements for the given array
function createContactEls(contacts) {
  contacts.forEach(createContactEl);
}

// Find in DOM, update contact element to match dataset data, update sorted position
function updateContactEl(contactId) {
  const contact = getContactEl(contactId);

  function setAllTextContent(querySelector, text) {
    contact.querySelectorAll(querySelector).forEach((el) => {
      el.textContent = text;
    });
  }
  setAllTextContent("[data-contact-first-name]", contact.dataset.firstName);
  setAllTextContent("[data-contact-last-name]", contact.dataset.lastName);
  setAllTextContent("[data-contact-phone-number]", contact.dataset.phoneNumber);
  setAllTextContent("[data-contact-company]", contact.dataset.company);
  setAllTextContent("[data-contact-email]", contact.dataset.email);

  sortContactEl(contactId);
}

// Delete a contact element from a contact ID
function deleteContactEl(contactId) {
  contactsTbody.querySelector(`[data-contact-id="${contactId}"]`).remove();
}

// Delete all contact elements
function deleteContactEls() {
  contactsTbody.replaceChildren();
}

// -- Search -------------------------------------------------------------------

const searchForm = document.querySelector("[data-search-form]");

async function handleSearch(clearPreviousContacts) {
  if (progress.searching) return;
  progress.searching = true;

  const fields = new FormData(searchForm);

  // Get url with potential queries
  let url = new URL("/api/contacts", window.location.origin);

  const query = fields.get("search-query");
  if (query) {
    url.searchParams.set("query", query);
  }

  // Only use after_id if we're doing a scroll-based search
  if (!clearPreviousContacts) {
    const afterId = contactsTbody.lastElementChild?.dataset.contactId;
    if (afterId) {
      url.searchParams.set("after_id", afterId);
    }
  }

  url = url.pathname + url.search;

  try {
    const data = await requestJson(url, {
      method: "GET",
    });

    if (clearPreviousContacts) {
      deleteContactEls();
    }
    createContactEls(data.contacts);
  } catch (failure) {
    console.log(failure);
  }

  progress.searching = false;
}

// Perform an initial full search on page load, then connect main search event
await handleSearch(false);
searchForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  await handleSearch(true);
});

// Load more contacts only if the bottom of the page is detected
async function ensureNewContactsLoaded() {
  const scrolledTo = Math.ceil(window.innerHeight + window.scrollY);
  const bottomPosition = document.documentElement.scrollHeight;

  if (scrolledTo >= bottomPosition - 96) { // Add a 1 inch buffer
    await handleSearch(false);
  }
}
// Scroll event can trigger this check. Delete event also triggers it later on
window.addEventListener("scroll", ensureNewContactsLoaded);

// -- Input Pane ---------------------------------------------------------------

const inputPane = document.querySelector("[data-input-pane]");
const inputForm = document.querySelector("[data-input-form]");
let inputMode = null; // "create" || "update"
let currentUpdateContactId = null;
const inputPaneSubmitButton = inputPane.querySelector('button[type="submit"]');
const inputPaneCancelButton = inputPane.querySelector('button[type="button"]');

inputForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const fields = new FormData(inputForm);

  const contactData = {
    first_name: fields.get("input-first-name"),
    last_name: fields.get("input-last-name"),
    phone_number: fields.get("input-phone-number"),
    company: fields.get("input-company"),
    email: fields.get("input-email"),
  }

  if (inputMode === "create") {
    await handleCreate(contactData);
  } else if (inputMode === "update") {
    await handleUpdate(contactData);
  }
});

function handleCreatePaneOpen() {
  handleInputPaneClose(); // Close it first to reset inputs
  inputMode = "create";
  inputPaneSubmitButton.innerText = "Create";
  inputPane.hidden = false;
}
function handleUpdatePaneOpen(contactId) {
  const contact = getContactEl(contactId);

  // Populate pre-existing values
  document.getElementById("input-first-name").value = contact.dataset.firstName;
  document.getElementById("input-last-name").value = contact.dataset.lastName;
  document.getElementById("input-phone-number").value = contact.dataset.phoneNumber;
  document.getElementById("input-company").value = contact.dataset.company;
  document.getElementById("input-email").value = contact.dataset.email;

  inputMode = "update";
  inputPaneSubmitButton.innerText = "Save";
  currentUpdateContactId = contactId;
  inputPane.hidden = false;
}

function handleInputPaneClose() {
  inputPane.hidden = true;
  document.getElementById("input-first-name").value = "";
  document.getElementById("input-last-name").value = "";
  document.getElementById("input-phone-number").value = "";
  document.getElementById("input-company").value = "";
  document.getElementById("input-email").value = "";
}

inputPaneCancelButton.addEventListener("click", handleInputPaneClose);

// -- Create -------------------------------------------------------------------

const createButton = document.querySelector("[data-create-button]");
createButton.addEventListener("click", handleCreatePaneOpen);

async function handleCreate(inputData) {
  if (progress.creating) return;
  progress.creating = true;

  try {
    const responseData = await requestJson("/api/contacts", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(inputData),
    });

    handleInputPaneClose(); // Only hide pane if create successful
    inputData.contact_id = responseData.contact_id;
    createContactEl(inputData);
  } catch (failure) {
    console.log(failure);
  }

  progress.creating = false;
}

// -- Update -------------------------------------------------------------------

async function handleUpdate(newContactData) {
  if (progress.updating) return;
  progress.updating = true;

  try {
    await requestJson(`/api/contacts/${currentUpdateContactId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(newContactData),
    });

    handleInputPaneClose(); // Only hide pane if update successful

    const contact = getContactEl(currentUpdateContactId);
    contact.dataset.firstName = newContactData.first_name;
    contact.dataset.lastName = newContactData.last_name;
    contact.dataset.phoneNumber = newContactData.phone_number;
    contact.dataset.company = newContactData.company ?? "";
    contact.dataset.email = newContactData.email ?? "";
    updateContactEl(currentUpdateContactId);
  } catch (failure) {
    console.log(failure);
  }

  progress.updating = false;
}

// -- Delete -------------------------------------------------------------------

async function handleDelete(contactId) {
  if (progress.deleting) return;
  progress.deleting = true;

  try {
    await requestJson(`/api/contacts/${contactId}`, {
      method: "DELETE",
    });

    deleteContactEl(contactId);

    // Delete can bring the bottom of the page into view, so ensure new contacts are loaded
    await ensureNewContactsLoaded();
  } catch (failure) {
    console.log(failure);
  }

  progress.deleting = false;
}
