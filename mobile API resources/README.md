# HRM Employee Mobile App API (v1)

API for the employee mobile app. An employee signs in with their e-mail and password. They can then:

- reset a forgotten password with an e-mailed code, or change it;
- see and update their profile;
- see their assigned assets;
- read and download their payslips;
- clock in and out with a photo and location;
- mark breaks;
- see their attendance as a list over any period, or as a month calendar;
- see a calendar list of holidays, their leave and events;
- see their leave balance, apply for leave, and follow their applications.

Every call works on **the signed-in employee only**. There is no way to read or change another employee's data.

Files in this folder:

| File | What it is |
|---|---|
| `README.md` | This document |
| `HRM-Mobile-API.postman_collection.json` | Postman collection: every endpoint, ready to run. Login saves the token for the other calls. |
| `HRM-Mobile-API.postman_environment.json` | Postman environment: `base_url`, `email`, `password`, plus `new_password` and `reset_code` for the password calls. `token`, `leave_type_id`, `leave_id`, `payslip_id` and `asset_id` are filled in by the requests. |

---

## 1. Basics

**Base URL**

```
https://<your-hrm-domain>/api/mobile/v1
```

On a local XAMPP/WAMP install it is `http://localhost/hrm_portal/api/mobile/v1`.

**Headers on every call**

| Header | Value |
|---|---|
| `Accept` | `application/json` |
| `Authorization` | `Bearer <token>`. Needed on every call except login. |

Use `multipart/form-data` for clock-in and clock-out, because they carry a photo. Other `POST`s accept form data or JSON.

**Every answer has the same shape**

```json
{ "success": true, "message": "OK", "data": { } }
```

**Errors have the same shape**, with `success: false`. A validation error also lists the problem fields:

```json
{ "success": false, "message": "The email must be a valid email address.", "errors": { "email": ["The email must be a valid email address."] } }
```

| HTTP status | Meaning | What the app should do |
|---|---|---|
| 200 / 201 | Done (201 = something was created) | |
| 401 | Not signed in, wrong password, or the token was revoked | Send the user to the login screen |
| 403 | This account cannot use the app (deactivated, login disabled, or not an employee) | Show `message` and log out |
| 404 | Not found, or not this employee's | Show `message` |
| 409 | Not possible right now, e.g. already clocked in, or no break running | Show `message`, then refresh `GET /attendance/today` |
| 422 | Something sent is wrong or missing | Show `message`, or `errors` per field |
| 429 | Too many attempts | Wait a minute |
| 500 | Server problem | Show `message` and retry later |

**Dates and times**
- Times are ISO 8601 in the company's time zone (IST), e.g. `2026-10-27T09:31:00+05:30`.
- Dates are `YYYY-MM-DD`.
- The calendar's `first_in` and `last_out` are `HH:MM`.
- The **server's clock** decides clock-in and clock-out times; the phone's time is never used.

**Money** is in rupees, as numbers with up to 2 decimals.

---

## 2. Sign in, out and passwords

### POST `/auth/login`

| Field | Required | Notes |
|---|---|---|
| `email` | yes | The employee's e-mail |
| `password` | yes | Their HRM password, the same as on the website |
| `device_name` | no | Shown to HR as the token name, e.g. `"Pixel 7 - Android 14"` |

```json
{
  "success": true,
  "message": "Logged in.",
  "data": {
    "token": "12|b8qaYqERoVG1z9KYeE4nc82EJGQ1VOTkDmBplO8d278d9ff1",
    "token_type": "Bearer",
    "employee": { "id": 4919, "name": "Test Employee", "email": "test.employee@company.com", "employee_id": "TEST001", "photo_url": "https://…/img/gravatar.png" }
  }
}
```

- Save `token` securely: Android Keystore or iOS Keychain. Send it as `Authorization: Bearer <token>`.
- The token does not expire on its own. It stops working:
  - on logout;
  - if HR deactivates the employee or disables their login. The next call then answers `403`.
- **5 wrong passwords** for the same e-mail from the same network lock sign-in for a minute (`429`).
- Wrong e-mail or password answers `401`, *"The e-mail or password is not correct."*
- Deactivated employees, employees whose login is disabled, and the Super Admin account get `403`.

### POST `/auth/logout`
Ends this device's token. No body.

### Forgot password

A forgotten password is reset with a **6-digit code sent by e-mail**, typed into the app. The website's "Forgot password" link is unchanged.

1. **POST `/auth/forgot-password`**: `email`. The answer is always the same, whether or not the e-mail belongs to anyone:
   ```json
   { "success": true, "message": "If this e-mail belongs to an employee, a 6-digit code has been sent to it.", "data": { "expires_in_minutes": 15 } }
   ```
   At most 3 requests per 10 minutes per e-mail (`429` after that). Each new request replaces the previous code.
2. **POST `/auth/verify-reset-code`** (optional): `email`, `code`. Checks the code before you show the new-password screen. It answers `200` if the code is correct and `422` if it is not.
3. **POST `/auth/reset-password`**: `email`, `code`, `password`, `password_confirmation`. On success the employee is **signed out on every device**. Then log in again.

- The code lasts **15 minutes**, works **once**, and allows **5 wrong tries**. After that: `422` *"The code has expired. Ask for a new one."*
- Password: at least 8 characters, at most 50.

### POST `/auth/change-password` (signed in)

| Field | Required | Notes |
|---|---|---|
| `current_password` | yes | |
| `password` | yes | 8–50 characters, different from the current one |
| `password_confirmation` | yes | |

`200` *"Your password has been changed."* The employee's **other devices are signed out**. This device's token keeps working. A wrong current password answers `422` with `errors.current_password`.

---

## 3. Profile

### GET `/me`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 4919,
    "name": "Test Employee",
    "employee_id": "TEST001",
    "photo_url": "https://…/avatar/abc.png",
    "email": "test.employee@company.com",
    "mobile": "+91 9876543210",
    "gender": "male",
    "designation": "CAD Engineer",
    "division": "Scan to CAD Department",
    "sub_division": "CAD",
    "reporting_manager": { "id": 4577, "name": "A S V N Murthy" },
    "employment_type": "full_time",
    "date_of_joining": "2025-01-01",
    "probation_end_date": null,
    "date_of_birth": "1995-06-14",
    "marital_status": "married",
    "marriage_date": "2020-02-14",
    "address": "Murali Nagar, Visakhapatnam",
    "emergency_contacts": [
      { "name": "R. Kumar", "relation": "Father", "mobile": "9876500000", "email": null }
    ],
    "shift_today": { "name": "General Shift", "type": "strict", "start": "09:30", "end": "18:30", "working_day": true }
  }
}
```

- `division` / `sub_division`: the employee's department. A department under a parent is a sub division, so `division` is the parent and `sub_division` is theirs. For a top-level department, `sub_division` is `null`.
- `probation_end_date` is `null` when the employee is not on probation.
- Any field can be `null` when HR has not filled it in.
- `mobile` is for display. `mobile_number` and `country_phonecode` hold the stored values, for the edit form.

### POST `/me` (multipart/form-data): update my profile

Changes **only the fields sent**. The answer is the full profile, the same as `GET /me`, with the message *"Profile updated."*

| Field | Notes |
|---|---|
| `mobile` | Digits only, 6–15 |
| `country_phonecode` | e.g. `91` |
| `gender` | `male`, `female` or `others` |
| `date_of_birth` | `YYYY-MM-DD`, not in the future |
| `marital_status` | `single`, `married`, `widower`, `widow`, `separate`, `divorced` or `engaged` |
| `marriage_date` | `YYYY-MM-DD`. Required when `married`, and cleared for any other status. |
| `address` | Up to 500 characters |
| `photo` | Profile photo: jpg / png / webp, max 2 MB |
| `remove_photo` | `1` removes the photo |

Name, e-mail (the sign-in), department, designation, manager and employment dates are managed by HR. They cannot be changed from the app.

---

## 4. Assets

### GET `/assets`
Assets the employee holds **now**.

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 94, "name": "Laptop", "asset_code": "LT-0042", "type": "Laptop",
      "brand": "Dell", "model": "Latitude 5440", "serial_number": "5CG3201ABC",
      "image_url": "https://…/asset-images/abc.jpg",
      "assigned_on": "2026-10-01", "expected_return_on": null
    }
  ]
}
```

An empty list means they hold nothing.

### GET `/assets/{id}`
One asset in detail. This opens if the asset is assigned to the employee now, or was in the past. Anything else answers `404`.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 94, "name": "Laptop", "asset_code": "LT-0042", "type": "Laptop",
    "brand": "Dell", "model": "Latitude 5440", "serial_number": "5CG3201ABC",
    "condition": "good", "status": "assigned",
    "warranty_until": "2028-03-31", "next_service_on": null,
    "image_url": "https://…/asset-images/abc.jpg", "notes": null,
    "held_by_me": true,
    "current_assignment": {
      "assigned_on": "2026-10-01", "expected_return_on": null,
      "condition_at_issue": "good", "issue_notes": "With charger and bag",
      "returned_on": null, "condition_at_return": null, "return_notes": null
    },
    "my_assignments": [ { "assigned_on": "2026-10-01", "returned_on": null, "…": "same fields as current_assignment" } ]
  }
}
```

`my_assignments` lists only **this employee's** assignments of the asset, newest first.

---

## 5. Payslips

Only **issued** payslips appear, meaning approved or paid by payroll. These are the same payslips as *My Payslips* on the website. Drafts and revoked payslips never show.

### GET `/payslips`
| Query | Required | Notes |
|---|---|---|
| `year` | no | e.g. `2026`: payslips whose period ends in that year. Leave it out for all. |

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 65, "payslip_number": "AROW/2026-27/000001", "month": "October - 2026",
      "period_start": "2026-09-21", "period_end": "2026-10-20", "net_pay": 28865, "status": "approved" }
  ]
}
```

Newest first. The pay period runs from the 21st to the 20th. `net_pay` is the take-home amount, the same figure printed on the payslip.

### GET `/payslips/{id}`
The full payslip as data, for drawing it in the app.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 65,
    "payslip_number": "AROW/2026-27/000001",
    "month": "October - 2026",
    "period_start": "2026-09-21",
    "period_end": "2026-10-20",
    "status": "approved",
    "issued_at": "2026-10-24T15:01:59+05:30",
    "paid_at": null,
    "attendance": { "working_days": 30, "days_present": 29, "lop_days": 1, "per_day": 900.53 },
    "earnings": [
      { "label": "Basic", "amount": 12000 },
      { "label": "House Rent Allowance", "amount": 4800 },
      { "label": "Conveyance Allowance", "amount": 800 },
      { "label": "Leave Travel Allowance", "amount": 1200 },
      { "label": "Children's Education Allowance", "amount": 1200 },
      { "label": "Special Allowance", "amount": 10000 },
      { "label": "Incentive", "amount": 1500 },
      { "label": "OT encashed (20h)", "amount": 2500 }
    ],
    "total_earnings": 34750,
    "deductions": [
      { "label": "Employee PF", "amount": 1392 },
      { "label": "Professional Tax", "amount": 200 },
      { "label": "Loss of Pay", "amount": 901 },
      { "label": "Salary Advance", "amount": 2000 }
    ],
    "total_deductions": 4493,
    "employer_contributions": [ { "label": "Employer PF", "amount": 1392 }, { "label": "Employer ESI", "amount": 0 } ],
    "total_employer_contributions": 1392,
    "leave_encashment": null,
    "overtime": { "encashed_hours": 20, "encashed_amount": 2500, "lop_days_covered": 2, "balance_hours": 66.92 },
    "net_pay": 28865,
    "net_pay_with_encashment": 28865,
    "download_url": "https://…/api/mobile/v1/payslips/65/download"
  }
}
```

- `earnings` and `deductions` contain only the lines this payslip has. A line such as LTA, ESI or TDS is left out when it is zero.
- **Net pay** is `total_earnings − total_deductions − total_employer_contributions`. The employer's PF and ESI are part of the CTC, so the payslip deducts them too.
- `leave_encashment` is `{ "days": 3, "amount": 2700 }` when leave was paid out on this payslip, otherwise `null`. `net_pay_with_encashment` includes it.
- `overtime` is `null` when the employee has no overtime bank.

### GET `/payslips/{id}/download`
The payslip as a **PDF**: the same document the website and e-mail give.
- Response is `application/pdf`, with the file name in `Content-Disposition`.
- Send the `Authorization` header. The link does not work in a browser without the token.
- A payslip that isn't theirs, or isn't issued, answers `404` in JSON.

---

## 6. Attendance

### How the app should drive the clock screen
1. On opening the screen, call **`GET /attendance/today`**. It tells you whether the employee is clocked in, whether a break is running, and whether a photo or location is needed.
2. Show **Clock in** or **Clock out**, plus **Start break** or **End break**, from `clocked_in` and `on_break`.
3. After every action, call `GET /attendance/today` again, or use the action's own answer.

The app follows the **same Attendance Settings as the website's clock-in**:
- **Employees may clock in themselves.** When HR turns this off, clock-in answers `403`. Clock-out still works.
- **IP check** and **office radius.** When HR turns either on, a punch from outside answers `403`. The radius is measured from the office picked (`location_id`), otherwise the employee's office, otherwise the default office. Both checks are skipped when `work_from_type` is `home`, as on the website.
- **Photo / location required.** See `requirements` below.

The shift's clock-in window is **not** applied. The app may clock in at any hour, the same as the website when "show the clock-in button" is on.

### GET `/attendance/today`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "date": "2026-10-27",
    "shift": { "name": "General Shift", "type": "strict", "start": "09:30", "end": "18:30", "working_day": true },
    "clocked_in": true,
    "open_session": { "id": 17987, "clock_in": "2026-10-27T09:12:06+05:30", "clock_out": null, "source": "mobile_app", "work_from": "office", "minutes": null },
    "on_break": true,
    "running_break": { "id": 5, "type": "lunch", "label": "Lunch break", "note": "canteen", "started_at": "2026-10-27T13:02:09+05:30", "ended_at": null, "minutes": null },
    "sessions": [ { "id": 17987, "clock_in": "2026-10-27T09:12:06+05:30", "clock_out": null, "source": "mobile_app", "work_from": "office", "minutes": null } ],
    "breaks": [ { "id": 5, "type": "lunch", "label": "Lunch break", "note": "canteen", "started_at": "2026-10-27T13:02:09+05:30", "ended_at": null, "minutes": null } ],
    "worked_minutes": 230,
    "requirements": { "photo": "optional", "location": "optional" },
    "can_clock_in": true,
    "office_radius_m": null,
    "offices": [ { "id": 1, "name": "Visakhapatnam, Andhra Pradesh 530007", "is_default": true, "latitude": 17.7462, "longitude": 83.2631 } ]
  }
}
```

- `can_clock_in: false`: HR has turned off self clock-in. Hide the **Clock in** button.
- `office_radius_m`: the allowed distance from the office in metres, or `null` when the distance is not checked.
- `offices`: the company's offices, for an optional "where are you" picker. Send the chosen `id` as `location_id`.

- **`requirements`** comes from *Admin → Settings → Attendance Settings*, for photo and location separately:
  - `off`: don't ask;
  - `optional`: ask, but allow skipping;
  - `mandatory`: clock-in and clock-out are refused without it.
- `shift.working_day: false` means today is a week-off for this employee. They can still clock in, and the time counts as overtime.
- `sessions` holds all of today's sessions, from the app, the website, the biometric device or HR. `worked_minutes` adds them up, counting an open session up to now.

### POST `/attendance/clock-in` (multipart/form-data)

| Field | Required | Notes |
|---|---|---|
| `location_id` | no | The office picked, from `offices` in `GET /attendance/today`. It is recorded, and the radius is measured from it. |
| `photo` | when `requirements.photo` = `mandatory` | Camera image file, **jpg / png / webp, max 2 MB**. Compress on the phone; around 1280 px is plenty. |
| `photo_base64` | (alternative to `photo`) | The same image as base64, with or without the `data:image/jpeg;base64,` prefix |
| `latitude`, `longitude` | when `requirements.location` = `mandatory` | Decimal degrees, e.g. `17.7231`, `83.3012` |
| `accuracy` | no | GPS accuracy in metres |
| `address` | no | Reverse-geocoded place, e.g. `"Murali Nagar, Visakhapatnam"`. Shown to HR. |
| `work_from_type` | no | `office` (default), `home` or `other` |
| `working_from` | when `work_from_type` = `other` | Free text, e.g. `"Client site – Gajuwaka"` |

**201**
```json
{ "success": true, "message": "Clocked in at 09:12 AM.",
  "data": { "id": 17987, "clock_in": "2026-10-27T09:12:06+05:30", "clock_out": null, "source": "mobile_app", "work_from": "office", "minutes": null } }
```

Refusals:
- `409`, *"You are already clocked in since …"*: already clocked in.
- `409`: the shift's limit on clock-ins per day has been reached.
- `422`: a required photo or location is missing (*"A photograph is required to clock in. Allow the camera and try again."*), or the image is unusable or too large.
- `403`: refused by Attendance Settings. Self clock-in is off (*"Clocking in yourself is turned off. Please contact HR."*), the device is not on an allowed IP (*"This is not an authorized device for clock-in or clock-out"*), or the employee is outside the office radius (*"Your current location is not with in the clock-in range"*).

Late marks, half days and lost pay are worked out later from the punches by the attendance rules, the same way as for the biometric device.

### POST `/attendance/clock-out` (multipart/form-data)
Same fields as clock-in. It closes the open session.
- `409`, *"You are not clocked in."*: there is no open session.
- `403`: outside the allowed IPs or the office radius, as for clock-in. The self clock-in switch does not block clock-out.
- A break still running is **ended automatically** at clock-out.

**200**
```json
{ "success": true, "message": "Clocked out at 06:41 PM.",
  "data": { "id": 17987, "clock_in": "2026-10-27T09:12:06+05:30", "clock_out": "2026-10-27T18:41:22+05:30", "source": "mobile_app", "work_from": "office", "minutes": 569 } }
```

A session left open for more than 20 hours, for example after forgetting to clock out, is no longer treated as "clocked in". The employee can clock in again the next day, and HR can correct the forgotten session.

### POST `/attendance/breaks/start`

| Field | Required | Notes |
|---|---|---|
| `type` | yes | `lunch`, `tea` or `other` |
| `note` | no | Up to 255 characters, e.g. the reason for an "other" break |
| `latitude`, `longitude` | no | Where the break started |

**201**: `data` is the break, e.g. `{ "id": 5, "type": "lunch", "label": "Lunch break", "started_at": "…", "ended_at": null, "minutes": null }`.

Refusals: `409` when not clocked in, or when a break is already running. `422` for an unknown `type`.

### POST `/attendance/breaks/end`

| Field | Required | Notes |
|---|---|---|
| `latitude`, `longitude` | no | Where the break ended |

**200**: `data` is the finished break, with `ended_at` and `minutes`. `409` when no break is running.

> Breaks are **recorded only**. They are shown to HR and in the app, but they do **not** reduce worked hours. A shift's hours already include breaks.

### GET `/attendance`: attendance over a period

| Query | Required | Notes |
|---|---|---|
| `from` | no | `YYYY-MM-DD`. Defaults to the 1st of `to`'s month. |
| `to` | no | `YYYY-MM-DD`. Defaults to today. |
| `status` | no | Only days with this `status`, e.g. `absent` or `paid_leave` (see the table below) |

Any period of up to **92 days**. A longer one answers `422`. Days are listed **newest first**. Each day has exactly the same fields as a day in `/attendance/calendar` below. `summary` covers the whole period, even when `status` filters the days, and adds `worked_minutes`.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "from": "2026-10-01",
    "to": "2026-10-30",
    "summary": { "days_counted": 30, "days_paid": 24, "lop_days": 6, "worked_days": 19.5, "week_offs": 4, "holidays": 2,
                 "paid_leave_days": 1, "unpaid_leave_days": 1, "absent_days": 4.5, "late_marks": 3, "late_half_days": 0, "worked_minutes": 10380 },
    "legend": [ { "status": "present", "label": "Present" }, "…" ],
    "days": [ { "date": "2026-10-30", "weekday": "Friday", "status": "in_progress", "…": "same fields as a calendar day" }, "…" ]
  }
}
```

### GET `/attendance/calendar`

| Query | Required | Notes |
|---|---|---|
| `month` | no | `1`–`12`; defaults to this month |
| `year` | no | e.g. `2026`; defaults to this year |

One entry per day of the month. Days are counted **exactly as payroll counts them**, so the app, the HR screens and the payslip agree.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "month": 10,
    "year": 2026,
    "summary": {
      "days_counted": 27, "days_paid": 18, "lop_days": 9,
      "worked_days": 11.5, "week_offs": 4, "holidays": 2,
      "paid_leave_days": 1, "unpaid_leave_days": 1, "absent_days": 7.5,
      "late_marks": 3, "late_half_days": 0
    },
    "legend": [ { "status": "present", "label": "Present" }, { "status": "half_day", "label": "Half day" }, "…" ],
    "days": [
      {
        "date": "2026-10-20", "weekday": "Tuesday",
        "status": "holiday", "status_label": "Holiday (paid)", "paid_day": 1,
        "shift": "General Shift", "shift_hours": "09:30–18:30",
        "first_in": null, "last_out": null, "worked_minutes": 0,
        "late": false, "late_note": null, "overtime_minutes": 0,
        "holiday": "Dusshera", "note": null,
        "leaves": [],
        "breaks": { "count": 0, "minutes": 0 }
      },
      {
        "date": "2026-10-14", "weekday": "Wednesday",
        "status": "paid_leave", "status_label": "Paid leave", "paid_day": 1,
        "first_in": null, "last_out": null, "worked_minutes": 0,
        "leaves": [ { "type": "Casual Leave", "duration": "single", "half": null, "status": "approved", "paid": true } ],
        "…": "other fields as above"
      },
      {
        "date": "2026-10-13", "weekday": "Tuesday",
        "status": "present", "status_label": "Present", "paid_day": 1,
        "first_in": "09:41", "last_out": "18:52", "worked_minutes": 551,
        "late": true, "late_note": "late", "overtime_minutes": 0,
        "breaks": { "count": 2, "minutes": 48 },
        "…": "other fields as above"
      }
    ]
  }
}
```

**Day `status` values**: colour them as you like, and use `status_label` as the text.

| status | Meaning |
|---|---|
| `present` | Worked a full day |
| `half_day` | Worked half a day |
| `half_day_leave` | Worked half, on leave for the other half |
| `half_leave` | Half-day leave, the other half not worked |
| `week_off` | Weekly off (paid) |
| `holiday` | Holiday (paid). `holiday` holds its name. |
| `paid_leave` | On paid leave. `leaves` holds the type. |
| `lop_leave` | On unpaid leave (loss of pay) |
| `absent` | Not present and no leave (loss of pay) |
| `not_employed` | Before the joining date or after leaving |
| `in_progress` | Today, clocked in, day not complete yet |
| `upcoming` | A day that hasn't happened yet |

- `paid_day` is how much of the day is paid: `1`, `0.5` or `0`.
- `late: true` when the first punch was late. `late_note` says `"late"` or `"after the half-day mark"`.
- `leaves` also lists **pending** leave requests, with `status: "pending"`, so the app can show them on the calendar. Only approved leave changes the day's `status`.
- `summary.lop_days` is the days of the month so far that are not paid.

### GET `/attendance/day/{date}`
One day in full, for the day-detail screen. `date` is `YYYY-MM-DD`.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "day": { "date": "2026-10-27", "status": "present", "status_label": "Present", "first_in": "09:12", "last_out": "18:41", "…": "same fields as a calendar day" },
    "sessions": [
      {
        "id": 17987,
        "clock_in": "2026-10-27T09:12:06+05:30",
        "clock_out": "2026-10-27T18:41:22+05:30",
        "source": "mobile_app",
        "work_from": "office",
        "minutes": 569,
        "clock_in_photo_url": "https://…/storage/attendance-photos/4919/2026-10/20261027-091206-Jx8G9u.jpg",
        "clock_in_location": { "latitude": 17.7231, "longitude": 83.3012, "address": "Murali Nagar, Visakhapatnam" },
        "clock_out_photo_url": "https://…/storage/attendance-photos/4919/2026-10/20261027-184122-qpdwcE.jpg",
        "clock_out_location": { "latitude": 17.724, "longitude": 83.302, "address": "Murali Nagar, Visakhapatnam" }
      }
    ],
    "breaks": [ { "id": 5, "type": "lunch", "label": "Lunch break", "note": "canteen", "started_at": "…", "ended_at": "…", "minutes": 42 } ]
  }
}
```

- `source` says where the session came from:
  - `mobile_app`: this app;
  - `web`: the website;
  - `hr_or_device`: marked by HR, or brought in from the biometric device.
- Sessions from the biometric device have no photo or location (`null`).
- A date in the wrong format answers `404`.

---

## 7. Calendar

### GET `/calendar`
The employee's calendar as a **list in date order**. It includes:
- holidays that apply to the employee;
- the employee's own leave, approved and pending, with one entry per application;
- events the employee is invited to.

Attendance day by day is under `/attendance` (section 6).

| Query | Required | Notes |
|---|---|---|
| `month`, `year` | no | Defaults to this month |
| `from`, `to` | no | `YYYY-MM-DD`, instead of `month` and `year`. At most 366 days. |
| `types` | no | Comma-separated: any of `holiday`, `leave`, `event`. Defaults to all three. |

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "from": "2026-10-01",
    "to": "2026-10-31",
    "items": [
      { "type": "holiday", "id": 10, "title": "Gandhi Jayanthi", "date": "2026-10-02", "end_date": "2026-10-02", "start_time": null, "end_time": null, "status": null, "color": null, "details": null },
      { "type": "leave", "id": 42, "title": "Sick", "date": "2026-10-09", "end_date": "2026-10-09", "start_time": null, "end_time": null, "status": "approved", "color": "#DB1313", "details": { "days": 1, "reason": "Fever" } },
      { "type": "event", "id": 3, "title": "Town hall", "date": "2026-10-15", "end_date": "2026-10-15", "start_time": "16:00", "end_time": "17:00", "status": "pending", "color": "#1d82f5", "details": { "where": "Board room", "link": null, "description": null } }
    ]
  }
}
```

- **Leave** items:
  - `status` is `approved` or `pending`.
  - `date` to `end_date` is the span of the application, and `details.days` is how many days it uses.
  - A half day has `(first half)` or `(second half)` in its `title`.
  - `id` opens the application with `GET /leaves/{id}`.
- **Event** items have `start_time` and `end_time` (`HH:MM`, local time), and `details` holds the place and link.

---

## 8. Leave

### GET `/leaves/types`: leave types and balance
The types the employee **may apply for**, after the type's conditions (gender, probation, role and so on) are applied, with their balance.

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 2, "name": "Sick", "code": "SL", "color": "#DB1313", "paid": true, "unlimited": false,
      "allowed": 8, "used": 1, "remaining": 7, "pending": 0, "monthly_limit": null,
      "document": { "required": "over_days", "when_more_than_days": 2, "label": "medical certificate", "may_follow_within_days": 3 },
      "rules": [ "Medical certificate when longer than 2 day(s) (can follow within 3 day(s))" ]
    }
  ]
}
```

- `remaining` is `null` for unlimited types, e.g. LOP. `pending` is the days waiting for approval.
- `document.required`:
  - `none`;
  - `always`;
  - `over_days`: needed when the leave is longer than `when_more_than_days`.

  When `may_follow_within_days` is set, the document can be uploaded later on the website, before the leave is approved.
- `rules` lists the type's rules in words, ready to show on the apply form.

### POST `/leaves` (multipart/form-data): apply for leave

| Field | Required | Notes |
|---|---|---|
| `leave_type_id` | yes | From `/leaves/types` |
| `duration` | yes | `single`, `multiple`, `first_half` or `second_half` |
| `leave_date` | for `single` and half days | `YYYY-MM-DD` |
| `from_date`, `to_date` | for `multiple` | `YYYY-MM-DD`. Holidays in between are left out. |
| `reason` | yes | Up to 1000 characters |
| `files[]` | when the type needs a document | jpg, png, webp, pdf, doc or docx; 5 MB each; up to 5 |
| `mark_leave_anyway` | no | `1` to apply for a day that already has a clock-in. See below. |

The leave always goes in as **Pending**. It then moves through the approval steps set in Leave Settings (reporting manager → department head → HR), with the same e-mails and notifications as the website. Answers `201` with the application, the same as `GET /leaves/{id}`.

The same checks as the website apply:

| Answer | When |
|---|---|
| `422` *"Leave type not allowed for this employee."* | The type's conditions exclude the employee |
| `422` | Earned Leave before the required years of service |
| `422` | The type's rules: notice days, how far back, most days at a time, or a required document that is missing |
| `422` *"Leave limit exceeded for the selected leave type."* / *"Monthly Leave limit exceeded…"* | Over the balance or the monthly limit, for types that may not go over |
| `422` *"Please select another date. There is holiday on selected date."* | Every chosen day is a holiday |
| `409` *"You already have leave on 06 Nov 2026 (pending)…"* | A chosen day already has pending or approved leave. A half day only clashes with the same half. |
| `409` *"Your attendance is already marked on …"* | A full day already has a clock-in. Ask the employee, then send again with `mark_leave_anyway=1`. |

> One difference from the website: when a day already has leave, the website silently rejects the old leave and keeps the new one. The app **refuses** instead, so nothing already approved is undone from a phone.

### GET `/leaves`: my applications

| Query | Required | Notes |
|---|---|---|
| `status` | no | `pending`, `approved` or `rejected` |
| `year` | no | Leave dates in this year |
| `page`, `per_page` | no | Default `1` and `20`; `per_page` max 100 |

One entry per application, however many days it covers, newest first:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "applications": [
      {
        "id": 223, "unique_id": "HGNQnFKFw3vHTGrm",
        "leave_type": { "id": 1, "name": "Casual", "color": "#16813D" },
        "duration": "multiple", "half_day_type": null,
        "from_date": "2026-11-13", "to_date": "2026-11-15", "days": 3, "paid": true,
        "reason": "Trip",
        "status": "pending", "status_label": "Pending with reporting manager", "waiting_with": "Reporting manager",
        "reject_reason": null, "approve_note": null, "decided_at": null,
        "applied_at": "2026-10-30T10:06:36+05:30", "files_count": 1
      }
    ],
    "counts": { "pending": 1, "approved": 4, "rejected": 0 },
    "page": 1, "per_page": 20, "total": 5, "last_page": 1
  }
}
```

- `status` is the application's status. If HR decided its days separately, a pending day makes the whole application `pending`.
- `status_label` is ready to show:
  - `Pending with reporting manager`, `Pending with department head` or `Pending with HR`;
  - `Approved`;
  - `Rejected`.
- `reject_reason` holds HR's reason when the application was rejected.
- `counts` covers every page.

### GET `/leaves/{id}`: one application
Takes any leave id of the application. The answer has the same fields as the list, plus:
- `dates`: every day with its own status;
- `history`: the approval trail, `[{ "step": "HR", "action": "submitted|approved|rejected|…", "by": "…", "note": "…", "at": "…" }]`;
- `files`: `[{ "name", "url", "size" }]`.

An application that isn't this employee's answers `404`.

---

## 9. Quick reference

| Method | Path | Body |
|---|---|---|
| POST | `/auth/login` | email, password, device_name? |
| POST | `/auth/logout` | |
| POST | `/auth/forgot-password` | email |
| POST | `/auth/verify-reset-code` | email, code |
| POST | `/auth/reset-password` | email, code, password, password_confirmation |
| POST | `/auth/change-password` | current_password, password, password_confirmation |
| GET | `/me` | |
| POST | `/me` | multipart: mobile?, country_phonecode?, gender?, date_of_birth?, marital_status?, marriage_date?, address?, photo?, remove_photo? |
| GET | `/assets` | |
| GET | `/assets/{id}` | |
| GET | `/payslips?year=` | |
| GET | `/payslips/{id}` | |
| GET | `/payslips/{id}/download` | → PDF |
| GET | `/attendance?from=&to=&status=` | |
| GET | `/attendance/today` | |
| POST | `/attendance/clock-in` | multipart: photo / photo_base64, latitude, longitude, location_id?, accuracy?, address?, work_from_type?, working_from? |
| POST | `/attendance/clock-out` | same as clock-in |
| POST | `/attendance/breaks/start` | type, note?, latitude?, longitude? |
| POST | `/attendance/breaks/end` | latitude?, longitude? |
| GET | `/attendance/calendar?month=&year=` | |
| GET | `/attendance/day/{YYYY-MM-DD}` | |
| GET | `/calendar?month=&year=` or `?from=&to=`, `&types=` | |
| GET | `/leaves/types` | |
| POST | `/leaves` | multipart: leave_type_id, duration, leave_date / from_date + to_date, reason, files[]?, mark_leave_anyway? |
| GET | `/leaves?status=&year=&page=&per_page=` | |
| GET | `/leaves/{id}` | |

---

## 10. Notes for the server team
- **Forgot-password codes** are kept in the application cache (`CACHE_DRIVER`, `file` by default) for 15 minutes. The code e-mail goes out through the same mail settings as every other HRM e-mail.
- **Database:** the API needs one new migration, `2026_10_27_100001_attendance_break_logs`. It runs with the other migrations.
- **Login tokens:** stored in `personal_access_tokens` (Laravel Sanctum). HR can revoke a phone's access by disabling the employee's login.
- **Clock-in photos:** saved under `storage/app/public/attendance-photos/{employee}/{YYYY-MM}/`. The server needs the `public/storage` link (`php artisan storage:link`) for the photo URLs to open.
- **Address:** the base URL has **no `/public`** in it. The server's `.env` must have `APP_URL` without `/public`, e.g. `https://<domain>/arowanauk/hrm`. Old `/public/...` addresses are redirected.
- **Clock-in rules:** app clock-ins use the same photo/location settings as the website clock-in (*Attendance Settings*). They count in attendance and payroll like any other punch.
