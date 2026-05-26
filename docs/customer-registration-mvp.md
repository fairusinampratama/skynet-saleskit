# Customer Registration MVP

## Goal

Rebuild SalesKit around field customer registration for new eBilling customers.
The app should help technicians collect accurate customer identity,
address, area, and evidence data in the field, with KTP OCR used only to speed up
input. The final submitted data must be reviewed and structured enough to be used
by eBilling.

## Primary Users

- Technician: registers new customers, captures installation location details,
  and uploads supporting evidence in the field.
- Admin/back office: reviews submissions, corrects data, manages areas, and sends
  approved registrations to eBilling.

## Core Workflow

1. Technician starts a new customer registration.
2. Technician uploads or captures a KTP photo.
3. OCR extracts candidate identity and address fields.
4. Technician reviews and corrects extracted data.
5. Technician fills missing operational data such as phone number, service area,
   installation address, GPS coordinate, and notes.
6. Technician captures supporting evidence, such as house/location photo.
7. Registration is submitted for review.
8. Admin validates the registration.
9. Admin approves the registration and sends it to eBilling through the API.

## MVP Scope

The first useful version should include:

- Mobile-friendly new registration form for technicians.
- KTP photo upload.
- OCR result storage, even if OCR starts as a manual or placeholder step.
- Editable verified customer fields.
- Admin-managed SalesKit area selection with required eBilling area code mapping.
- GPS coordinate capture.
- Supporting photo evidence.
- Admin review screen.
- Registration status tracking.
- Queue-backed eBilling API adapter with mock implementation until the real API
  contract is available.

## Out of Scope for First Version

- Full automatic eBilling sync without admin review.
- Perfect KTP parsing.
- Complex task management.
- Lead interest tracking.
- Package or service plan selection.
- Payment or billing lifecycle management.
- Customer self-service portal.

## Data Groups

### Customer Identity

- Full name.
- NIK.
- Phone number.
- Email, optional.
- KTP photo.
- OCR raw text.
- OCR parsed fields.

### Address and Area

- KTP address.
- Installation/service address.
- Province.
- City or regency.
- District.
- Village.
- RT.
- RW.
- Postal code.
- GPS latitude.
- GPS longitude.
- Area or coverage code.

### Service Registration

- Registration source.
- Registered by technician.
- Assigned technician, optional.
- Notes.
- Status.
- Submitted at.
- Reviewed at.
- Reviewed by.

### Evidence

- KTP image.
- House or location photo.
- Optional additional documents.

## Suggested Statuses

- `draft`: Technician is still editing.
- `submitted`: Waiting for admin validation.
- `needs_revision`: Admin found missing or invalid data.
- `approved`: Validated by admin and allowed to sync.
- `syncing`: eBilling API job is running.
- `synced`: eBilling accepted the customer and returned a customer ID.
- `sync_failed`: eBilling sync failed and can be retried.
- `cancelled`: Registration is no longer valid.

## Suggested Data Model

### `customers`

Stores the verified customer profile used by the business.

- name
- nik
- phone
- email
- status

### `customer_documents`

Stores uploaded identity documents and OCR output.

- customer_id
- document_type, such as `ktp`
- original_file_path
- processed_file_path
- ocr_raw_text
- ocr_parsed_data
- verified_at

### `customer_addresses`

Stores both KTP and installation addresses.

- customer_id
- address_type, such as `ktp` or `installation`
- province
- city
- district
- village
- rt
- rw
- postal_code
- full_address
- latitude
- longitude

### `registrations`

Tracks the field registration process and eBilling readiness.

- customer_id
- registered_by
- reviewed_by
- area_id
- status
- notes
- submitted_at
- reviewed_at
- synced_at

### `areas`

Stores SalesKit-owned operational coverage areas and maps them to eBilling area
codes.

- name
- code
- ebilling_area_code
- province
- city
- district
- village
- active

## eBilling Questions

These must be answered before finalizing migrations and exports:

- What fields are required to create a customer in eBilling?
- Which fields are optional?
- Does eBilling require NIK?
- Does eBilling require KTP image upload or only customer data?
- Does eBilling use area code, village code, or custom coverage code?
- What is the required customer creation API payload format?
- Does eBilling generate customer IDs, or should SalesKit generate them?
- Are installation address and KTP address stored separately in eBilling?
- Are GPS coordinates supported?

## OCR Approach

OCR should prefill data, not automatically approve data.

Recommended first approach:

1. Store original uploaded/captured KTP image.
2. Store processed auto-cropped KTP image.
3. Store OCR raw text and parsed fields separately.
4. Show extracted values to the technician.
5. Require the user to confirm or correct fields.
6. Save confirmed data as verified customer data.

Possible OCR providers:

- Cloud OCR API: faster to implement and usually more accurate, but has privacy
  and recurring cost considerations.
- Local OCR, such as Tesseract: more private and cheaper, but likely weaker for
  KTP photos.
- Placeholder OCR service: fastest way to validate the workflow while keeping the
  provider integration replaceable.

## Implementation Direction

Keep Laravel and Filament, but change the app shape:

- Use Filament for admin review, user management, area management, and eBilling
  sync logs.
- Add a simpler mobile-first registration flow for technicians.
- Keep existing Indonesian administrative area data.
- Replace the lead-oriented customer form with registration-oriented entities.
- Keep task management only if it supports installation follow-up.

## First Build Milestone

The first milestone should prove that a technician can submit one complete
customer registration and an admin can send it to eBilling through the API
adapter.

Acceptance criteria:

- Technician can create a registration from mobile.
- KTP image can be uploaded.
- Customer identity and address can be entered or corrected.
- Area can be selected from active admin-managed areas.
- GPS coordinate and evidence photo can be captured.
- Admin can review the registration.
- Admin can approve or request revision.
- Admin can send approved registrations to the mock eBilling adapter.
