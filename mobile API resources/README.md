# HRM Employee Mobile App API (v1)

API for the employee mobile app. An employee signs in with their e-mail and password. They can then:

- see their profile;
- see their assigned assets;
- read and download their payslips;
- clock in and out with a photo and location;
- mark breaks;
- see their attendance as a month calendar.

Every call works on **the signed-in employee only**. There is no way to read or change another employee's data.

Files in this folder:

| File | What it is |
|---|---|
| `README.md` | This document |
| `HRM-Mobile-API.postman_collection.json` | Postman collection: every endpoint, ready to run. Login saves the token for the other calls. |
| `HRM-Mobile-API.postman_environment.json` | Postman environment: `base_url`, `email`, `password` |

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

## 2. Sign in and out

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

Every employee can clock in from the app. The location is **recorded, not checked**: there is no office-distance limit.

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
    "requirements": { "photo": "optional", "location": "optional" }
  }
}
```

- **`requirements`** comes from *Admin → Settings → Attendance Settings*, for photo and location separately:
  - `off`: don't ask;
  - `optional`: ask, but allow skipping;
  - `mandatory`: clock-in and clock-out are refused without it.
- `shift.working_day: false` means today is a week-off for this employee. They can still clock in, and the time counts as overtime.
- `sessions` holds all of today's sessions, from the app, the website, the biometric device or HR. `worked_minutes` adds them up, counting an open session up to now.

### POST `/attendance/clock-in` (multipart/form-data)

| Field | Required | Notes |
|---|---|---|
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

Late marks, half days and lost pay are worked out later from the punches by the attendance rules, the same way as for the biometric device.

### POST `/attendance/clock-out` (multipart/form-data)
Same fields as clock-in. It closes the open session.
- `409`, *"You are not clocked in."*: there is no open session.
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

## 7. Quick reference

| Method | Path | Body |
|---|---|---|
| POST | `/auth/login` | email, password, device_name? |
| POST | `/auth/logout` | |
| GET | `/me` | |
| GET | `/assets` | |
| GET | `/assets/{id}` | |
| GET | `/payslips?year=` | |
| GET | `/payslips/{id}` | |
| GET | `/payslips/{id}/download` | → PDF |
| GET | `/attendance/today` | |
| POST | `/attendance/clock-in` | multipart: photo / photo_base64, latitude, longitude, accuracy?, address?, work_from_type?, working_from? |
| POST | `/attendance/clock-out` | same as clock-in |
| POST | `/attendance/breaks/start` | type, note?, latitude?, longitude? |
| POST | `/attendance/breaks/end` | latitude?, longitude? |
| GET | `/attendance/calendar?month=&year=` | |
| GET | `/attendance/day/{YYYY-MM-DD}` | |

---

## 8. Notes for the server team
- **Database:** the API needs one new migration, `2026_10_27_100001_attendance_break_logs`. It runs with the other migrations.
- **Login tokens:** stored in `personal_access_tokens` (Laravel Sanctum). HR can revoke a phone's access by disabling the employee's login.
- **Clock-in photos:** saved under `storage/app/public/attendance-photos/{employee}/{YYYY-MM}/`. The server needs the `public/storage` link (`php artisan storage:link`) for the photo URLs to open.
- **Address:** the base URL has **no `/public`** in it. The server's `.env` must have `APP_URL` without `/public`, e.g. `https://<domain>/arowanauk/hrm`. Old `/public/...` addresses are redirected.
- **Clock-in rules:** app clock-ins use the same photo/location settings as the website clock-in (*Attendance Settings*). They count in attendance and payroll like any other punch.
