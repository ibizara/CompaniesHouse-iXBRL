# CompaniesHouse-iXBRL

This project facilitates the construction and submission of iXBRL filings to Companies House using PHP scripts. It handles envelope creation, document encoding, and step-by-step submission to the gateway in accordance with required XML formats.

## Prerequisites

- PHP 7.0+
- Write permissions in the project directory
- Access credentials and gateway configuration (provided by Companies House)

## Workflow

> ⚠️ **Important**: Each transaction requires a **unique `TransactionID`** to be submitted successfully.

Follow these steps to build and submit an iXBRL filing:

### 1. Update `build_ixbrl.php`

Edit the necessary variables in `build_ixbrl.php` to reflect the current company details, accounts data, etc.

### 2. Generate `output.xhtml`

Run the script to build the base XML structure:

```bash
php build_ixbrl.php
```

This will produce an `output.xhtml` file which can be validated at https://test-validator.companieshouse.gov.uk/xbrl_validate

### 3. Encode the XML in Base64

Use PHP to encode `output.xhtml` into base64 format:

```php
$encoded = base64_encode(file_get_contents('output.xhtml'));
```

### 4. Update `envelope.xml`

Replace the relevant fields in `envelope.xml`, including inserting the base64-encoded content into the appropriate element.

### 5. Post the envelope

Run `post.php` to submit the envelope:

```bash
php post.php
```

### 6. Update `status.xml`

Edit `status.xml`, ensuring to:

- Provide a valid and **unique `TransactionID`**
- Reflect any updated envelope reference or metadata

### 7. Replace `envelope.xml` with `status.xml` in `post.php`

In `post.php`, update the reference so that `status.xml` is the file being posted.

### 8. Submit `status.xml`

Run:

```bash
php post.php
```

### 9. Update `ack_status.xml`

Edit `ack_status.xml`, ensuring:

- A **new, unique `TransactionID`**

### 10. Replace `status.xml` with `ack_status.xml` in `post.php`

Update the `post.php` script to post the `ack_status.xml` file instead.

### 11. Final Submission

Run:

```bash
php post.php
```

## Notes

- Ensure every submission has a **different `TransactionID`**. Duplicate IDs will cause submission errors.
- This process mimics the standard E-filing gateway interactions and is provided for development/testing purposes.
- A more complete package will be released soon — this version serves as a working proof of concept.
