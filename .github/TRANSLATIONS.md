# Translating InvoiceMuse

InvoiceMuse is a multilingual application, and we rely on community contributions to keep translations up to date. If you want to help translate InvoiceMuse into your language, follow this guide.

## 🌍 Where Are Translations Managed?

InvoiceMuse inherits its language strings from InvoicePlane.

- Strings shared with InvoicePlane are translated by the InvoicePlane community on [Crowdin](https://crowdin.com/) under the project `FusionInvoice`.
- Strings specific to InvoiceMuse (anything that names InvoiceMuse or covers an InvoiceMuse-only feature) are updated through a pull request to the [InvoiceMuse repository](https://github.com/dnp-g2/InvoiceMuse).

## 🔹 How to Contribute on Crowdin

1. **Sign up for a Crowdin account** at [crowdin.com](https://crowdin.com/).
2. **Request access to the FusionInvoice project** by searching for `FusionInvoice`.
3. **Choose a language** from the available options. Languages follow **short codes** (e.g., `en`, `de`, `fr`).
4. **Start translating** missing strings or improving existing ones.
5. **Submit your translations** for review.

## 📜 Translation Guidelines

- Follow existing terminology to ensure consistency.
- Do **not** translate placeholders like `{invoice_number}` or `{client_name}`.
- Keep the formatting intact, especially in Markdown or HTML-based text.
- If unsure, ask in an [InvoiceMuse issue](https://github.com/dnp-g2/InvoiceMuse/issues) before making significant changes.

## 🛠️ Technical Details

- Translations are stored in `.php` language files inside `application/language/`.
- Directory is long form of the language, **lowercase** ('english', 'german', 'french')
- Each language has its **own folder** (`application/language/english/`, `application/language/german/`, etc.).
- The structure inside each folder should match the default **english (`english`) translation**.

## 💡 Need Help?

If you have any questions, open an [InvoiceMuse issue](https://github.com/dnp-g2/InvoiceMuse/issues) or ask in the translation discussions on Crowdin.

---
*Thank you for helping make InvoiceMuse accessible to a global audience!*
