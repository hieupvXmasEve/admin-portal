# Legacy Eloquent Models Classification Audit

**Scope:** app/Models/ (~110 files)  
**Method:** Grep-based reference counting in app/, routes/, database/, tests/, resources/  
**Date:** 2026-08-10

## Summary

| Category | Count | Action |
|----------|-------|--------|
| **DEAD** | 1 | Delete immediately |
| **STALE-DUPLICATE** | 30 | Replace with module imports, remove alias stub |
| **ALIVE-LEGACY** | 70 | Keep in app/Models (module migration backlog) |
| **ALIVE-CORE** | 9 | Keep (genuinely cross-cutting) |
| **TOTAL** | 110 | — |

---

## DEAD (0 external references)

| Model | Refs | File | Note |
|-------|------|------|------|
| **GraduationApplication** | 0 | app/Models/GraduationApplication.php | No factory, no usage. Safe delete. |

---

## STALE-DUPLICATE (Using class_alias — pending sweep)

30 models are deprecated alias shims. Real implementations live in modules.
Update callers to import from modules, then delete the app/Models stub.

### Engagement (16 models)

| Model | Refs | Module Path |
|-------|------|------------|
| Club | 15 | Modules/Engagement/Models/Club |
| ClubMember | 13 | Modules/Engagement/Models/ClubMember |
| ClubMemberRoleHistory | 5 | Modules/Engagement/Models/ClubMemberRoleHistory |
| Event | 46 | Modules/Engagement/Models/Event |
| EventParticipant | 8 | Modules/Engagement/Models/EventParticipant |
| Form | 61 | Modules/Engagement/Models/Form |
| FormResponse | 33 | Modules/Engagement/Models/FormResponse |
| FormResultVisibility | 5 | Modules/Engagement/Models/FormResultVisibility |
| FormSection | 16 | Modules/Engagement/Models/FormSection |
| FormSurvey | 8 | Modules/Engagement/Models/FormSurvey |
| FormTarget | 8 | Modules/Engagement/Models/FormTarget |
| FormVersion | 19 | Modules/Engagement/Models/FormVersion |
| QueryAssignment | 6 | Modules/Engagement/Models/QueryAssignment |
| QueryReply | 4 | Modules/Engagement/Models/QueryReply |
| QueryTicket | 5 | Modules/Engagement/Models/QueryTicket |
| QueryTopic | 6 | Modules/Engagement/Models/QueryTopic |

### Merchandise (7 models)

| Model | Refs | Module Path |
|-------|------|------------|
| GoldTransaction | 4 | Modules/Merchandise/Models/GoldTransaction |
| Merchandise | 38 | Modules/Merchandise/Models/Merchandise |
| MerchandiseImage | 6 | Modules/Merchandise/Models/MerchandiseImage |
| MerchandiseVariant | 9 | Modules/Merchandise/Models/MerchandiseVariant |
| RedemptionOrder | 9 | Modules/Merchandise/Models/RedemptionOrder |
| RedemptionOrderItem | 3 | Modules/Merchandise/Models/RedemptionOrderItem |
| StockMovement | 8 | Modules/Merchandise/Models/StockMovement |

### Facilities (4 models)

| Model | Refs | Module Path |
|-------|------|------------|
| Building | 4 | Modules/Facilities/Models/Building |
| Room | 25 | Modules/Facilities/Models/Room |
| RoomBooking | 21 | Modules/Facilities/Models/RoomBooking |
| RoomBookingAction | 4 | Modules/Facilities/Models/RoomBookingAction |

### Upload (3 models)

| Model | Refs | Module Path |
|-------|------|------------|
| ApplicationDocument | 58 | Modules/Upload/Models/ApplicationDocument |
| ApplicationDocumentType | 5 | Modules/Upload/Models/ApplicationDocumentType |
| UploadRecord | 3 | Modules/Upload/Models/UploadRecord |

### Finance (1 split-brain model)

| Model | Refs | Issue |
|-------|------|-------|
| **EgcRetakeDiscountLink** | 12 | Twin without alias; both exist (legacy + module). No class_alias() protection. Module is authoritative. |

---

## ALIVE-LEGACY (70 models, actively used, module migration backlog)

These models remain in app/Models but logically belong to module namespaces.
Keep as-is; eventual migration is architectural debt but not immediate risk.

### Academic (28 models)
AcademicHold (11), AcademicProgressionEvent (47), AcademicRecord (366), AcademicStanding (3), AcademicWarningSetting (10), AssessmentComponent (144), AssessmentComponentDetail (105), AssessmentComponentDetailScore (113), Attendance (75), ClassSession (187), CourseOffering (547), CourseRegistration (249), CourseRetakeRegistration (148), CurriculumModule (16), CurriculumUnit (57), CurriculumVersion (209), DeferCase (115), DeferCaseItem (10), EquivalentUnit (10), ExamResitAttempt (213), ExamResitSession (50), ExamRoomSlot (73), ExamRoomSlotInvigilator (25), GpaCalculation (77), GraduationRequirement (6), Lecture (165), Semester (1136), Unit (433)

### Finance (8 models)
BillingCycle (4), EmailConfiguration (55), EmailLog (45), ScholarshipDefinition (74), StudentScholarshipAward (87), TuitionPlan (51), TuitionPlanTerm (35), VoucherDefinition (17), VoucherApplication (17)

### StudentRegistry (16 models)
Enrollment (24), IeltsCertificate (24), ParentProfile (62), Student (1250), StudentActionAttachment (1), StudentActionLog (104), StudentApplication (135), StudentChange (4), StudentDecision (39), StudentFormAssignment (22), StudentFormSurvey (3), StudentSetting (1), StudentWarningLog (19), StudentWallet (14)

### Admissions (1 model)
ApplicationGuardian (58), ProgramChangeRequest (3), Program (340), Specialization (70)

### Other (17 models)
CanvasCourseMapping (30), CanvasIntegration (27), Department (45), DepartmentMembership (23), EgcBlock (111), Enrollment (24), EquivalentUnit (10), Module (45), ProgramChangeRequest (3)

---

## ALIVE-CORE (9 models, genuinely cross-cutting)

| Model | Refs | Rationale |
|-------|------|-----------|
| **User** | 1037 | Authentication core; used everywhere |
| **Campus** | 1036 | Tenancy boundary; used everywhere |
| **Semester** | 1136 | Temporal scope for all modules |
| **Role** | 189 | Permission system |
| **Permission** | 90 | Authorization |
| **RolePermission** | 51 | Auth mapping |
| **CampusUserRole** | 54 | Multi-tenancy + roles |
| **UserEmailPreference** | 90 | Email opt-out (cross-module) |
| **Option** | 6 | Form choice values (low refs, verify legacy survey) |

---

## Special Checks

### Notification Models
- **No app/Models/Notification.php** found.
- Module twins exist: app/Modules/Notification/Models/{NotificationEmailTemplate, NotificationDelivery, NotificationEventOutbox, NotificationMessage}.
- Legacy email: EmailConfiguration (55 refs), EmailLog (45 refs), UserEmailPreference (90 refs) still in app/Models—actively used, cross-module email pref handling.

### Cross-Module Imports in app/Models
Several legacy models import from Finance module (split-brain patterns):
- Student: imports DngPaymentRequest, BillingAccount
- BillingCycle: imports StudentInvoice
- CourseRegistration, CourseRetakeRegistration, DeferCase: import FinanceCharge
- EgcBlock, EgcRetakeDiscountLink: import InvoiceDiscount, InvoiceLine
- VoucherApplication: imports StudentInvoice

These indicate module boundaries need clarification (Finance models referenced from StudentRegistry, Academic legacy).

### Low-Reference Models (potential stale logic)
- Option (6), GraduationRequirement (6), AnswerOption (2): Verify if form/survey logic has been refactored to Engagement module.
- BillingCycle (4), StudentChange (4): Check if superseded by Finance billing logic or changelog patterns.
- ProgramChangeRequest (3), StudentFormSurvey (3): Likely legacy; confirm no active workflows.

---

## Recommendations

1. **Delete immediately:** GraduationApplication (1 file, 0 risk).
2. **Sweep class_alias stubs:** Batch update callers from app/Models/* to Modules/*/* namespace.
   - Highest traffic: Event (46), Form (61), ApplicationDocument (58), Room (25), RoomBooking (21).
   - Lowest: GoldTransaction (4), Building (4).
3. **Resolve EgcRetakeDiscountLink split-brain:** Decide if legacy or module model is canonical; add class_alias or remove duplicate.
4. **Audit low-ref logic:** Verify Option, GraduationRequirement, AnswerOption, BillingCycle usages are not orphaned.
5. **Clarify Finance-StudentRegistry boundary:** Cross-module imports suggest schema or ownership confusion; review ADR-0026/0027.

---

## Evidence
- Grep patterns: `use App\Models\ClassName`, `ClassName::`, `Models\ClassName`
- Scope: app/, routes/, database/factories, database/seeders, tests/, resources/
- Date scanned: 2026-08-10
