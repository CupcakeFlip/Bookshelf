/** Returns separate display fields while avoiding a duplicated headline prefix. */
export function bookText(book = {}) {
  const headline = typeof book.headline === "string" ? book.headline.trim() : "";
  const description = typeof book.description === "string" ? book.description.trim() : "";
  if (!headline) return {headline: "", description};
  if (!description) return {headline, description: ""};
  if (description === headline) return {headline, description: ""};
  if (description.startsWith(headline)) return {headline, description: description.slice(headline.length).replace(/^\s+/, "")};
  return {headline, description};
}
