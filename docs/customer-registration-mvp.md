# Customer Registration MVP

## Goal

Rebuild SalesKit around field customer registration.
The app should help technicians collect accurate customer identity,
address, area, and evidence data in the field, with KTP OCR used only to speed up
input. The final submitted data must be reviewed and stored as a complete
registration record.

## Primary Users

- Technician: registers new customers, captures installation location details,
  and uploads supporting evidence in the field.
- Admin/back office: reviews submissions, corrects data, manages areas, and
  approves or requests revisions.

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
9. Admin approves the registration or requests revision.

## MVP Scope

The first useful version should include:

- Mobile-friendly new registration form for technicians.
- KTP photo upload.
- OCR result storage, even if OCR starts as a manual or placeholder step.
- Editable verified customer fields.
- Admin-managed SalesKit area selection.
- Fixed package selection: 10MB, 15MB, 25MB, 30MB, 35MB, 50MB, 100MB, or 200MB.
- GPS coordinate capture.
- Supporting photo evidence.
- Admin review screen.
- Registration status tracking.

## Out of Scope for First Version

- Perfect KTP parsing.
- Complex task management.
- Lead interest tracking.
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
- Package.

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
- `approved`: Validated by admin.
- `cancelled`: Registration is no longer valid.

## Suggested Data Model

### `registrations`

Tracks the full field registration process, verified identity data, address data,
KTP OCR output, selected package, and review status. SalesKit does not keep a
separate customer table for the MVP.

- name
- nik
- phone
- email
- ktp_full_address
- installation_full_address
- province
- city
- district
- village
- rt
- rw
- postal_code
- latitude
- longitude
- ktp_original_file_path
- ktp_processed_file_path
- ktp_ocr_raw_text
- ktp_ocr_parsed_data
- ktp_verified_at
- package
- registered_by
- reviewed_by
- area_id
- status
- notes
- submitted_at
- reviewed_at

### `areas`

Stores SalesKit-owned operational coverage areas.

- name
- code
- province
- city
- district
- village
- active

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

- Use Filament for admin review, user management, and area management.
- Add a simpler mobile-first registration flow for technicians.
- Keep existing Indonesian administrative area data.
- Replace the lead-oriented customer form with registration-oriented entities.
- Keep task management only if it supports installation follow-up.

## First Build Milestone

The first milestone should prove that a technician can submit one complete
customer registration and an admin can review it.

Acceptance criteria:

- Technician can create a registration from mobile.
- KTP image can be uploaded.
- Customer identity and address can be entered or corrected.
- Area can be selected from active admin-managed areas.
- Package can be selected from the fixed package list.
- GPS coordinate and evidence photo can be captured.
- Admin can review the registration.
- Admin can approve or request revision.
