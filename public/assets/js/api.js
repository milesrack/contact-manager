export async function requestJson(url, options = {}) {
  const { redirectOnUnauthorized = false, ...fetchOptions } = options;
  let response;
  try {
    response = await fetch(url, {
      ...fetchOptions,
      headers: { Accept: "application/json", ...options.headers },
    });
  } catch {
    throw new Error("Unable to connect. Please try again.");
  }

  if (response.status === 401 && redirectOnUnauthorized) {
    window.location.assign("/login");
    throw new Error("Your session has expired. Please log in again.");
  }

  let data;
  try {
    data = await response.json();
  } catch {
    throw new Error("Unexpected server response. Please try again.");
  }

  if (!response.ok) {
    throw new Error(
      typeof data?.error === "string"
        ? data.error
        : "Something went wrong. Please try again.",
    );
  }
  return data;
}
