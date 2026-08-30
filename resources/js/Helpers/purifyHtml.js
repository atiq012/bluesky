import DOMPurify from "dompurify";

const PURIFY_OPTIONS = {
    ALLOWED_TAGS: ["p", "br", "strong", "em", "u", "s", "ol", "ul", "li", "a", "blockquote", "code", "pre"],
    ALLOWED_ATTR: ["href", "target", "rel", "class"],
};

// Quill/helpdesk HTML only — never use on plain text fields.
export function purifyHtml(html) {
    return DOMPurify.sanitize(String(html ?? ""), PURIFY_OPTIONS);
}
