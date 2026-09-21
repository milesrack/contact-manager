export async function requestJson(url, options = {}) {
  let response;
  try {
    response = await fetch(url, {
      ...options,
      headers: { Accept: "application/json", ...options.headers },
    });
  } catch {
    throw new Error("Unable to connect. Please try again.");
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
