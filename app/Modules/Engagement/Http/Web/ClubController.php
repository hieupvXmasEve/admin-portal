<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web;

use App\Constants\ClubRoutes;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Modules\Engagement\Actions\ClubOperations;
use App\Modules\Engagement\Http\Requests\AddClubMemberRequest;
use App\Modules\Engagement\Http\Requests\AssignPresidentRequest;
use App\Modules\Engagement\Http\Requests\CreateClubRequest;
use App\Modules\Engagement\Http\Requests\UpdateClubRequest;
use App\Modules\Engagement\Models\Club;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function __construct(
        protected ClubOperations $clubService,
        private readonly StudentReferenceReader $studentReferenceReader,
        private readonly StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {
        $this->middleware('can:view_clubs')->only(['index', 'show']);
        $this->middleware('can:create_clubs')->only(['create', 'store']);
        $this->middleware('can:edit_clubs')->only(['edit', 'update']);
        $this->middleware('can:delete_clubs')->only(['destroy']);
        $this->middleware('can:edit_clubs')->only(['assignPresident', 'addMember']);
    }

    /**
     * Display a listing of clubs with search and filtering.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'status' => 'nullable|string|in:active,inactive',
            'sort' => 'nullable|string|in:name,campus_id,status,founded_date,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $page = (int) $request->query('page', 1);
        $cacheKey = 'clubs:index:'.md5(json_encode([
            'page' => $page,
            'per_page' => $validated['per_page'] ?? 15,
            'search' => $validated['search'] ?? null,
            'campus_id' => $validated['campus_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'asc',
        ]));

        $clubs = Cache::tags(['clubs', 'clubs.index'])->rememberForever($cacheKey, function () use ($validated, $page) {
            return Club::query()
                ->with(['campus', 'president.student'])
                ->withCount(['activeMembers', 'pendingApplications'])
                ->when($validated['search'] ?? null, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('campus', function ($campusQuery) use ($search) {
                                $campusQuery->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($validated['campus_id'] ?? null, function ($query, $campusId) {
                    $query->where('campus_id', $campusId);
                })
                ->when($validated['status'] ?? null, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                    $direction = $validated['direction'] ?? 'asc';
                    if ($sort === 'campus_id') {
                        $query->join('campuses', 'clubs.campus_id', '=', 'campuses.id')
                            ->orderBy('campuses.name', $direction)
                            ->select('clubs.*');
                    } else {
                        $query->orderBy($sort, $direction);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $page);
        })->withQueryString();

        // Get campuses for filter dropdown
        $campuses = Cache::tags(['campuses'])->rememberForever('campuses:dropdown', function () {
            return Campus::select('id', 'name')->orderBy('name')->get();
        });

        return Inertia::render('Clubs/Index', [
            'clubs' => $clubs,
            'campuses' => $campuses,
            'filters' => $request->only(['search', 'campus_id', 'status', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new club.
     */
    public function create(): Response
    {
        return Inertia::render('Clubs/Create');
    }

    /**
     * Store a newly created club.
     */
    public function store(CreateClubRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $presidentStudentId = $validated['president_student_id'];
        unset($validated['president_student_id']);

        // Add campus_id from session
        $validated['campus_id'] = session('current_campus_id');

        $club = $this->clubService->createClub($validated, $presidentStudentId);

        // Invalidate club caches after creation
        Cache::tags(['clubs'])->flush();

        return redirect()->route(ClubRoutes::INDEX)->with('success', 'Club created successfully and president assigned.');
    }

    /**
     * Display the specified club with detailed information.
     */
    public function show(Request $request, Club $club): Response
    {
        // Validate member search and pagination parameters
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,pending,rejected,left,banned',
            'role' => 'nullable|string|in:president,vice_president,secretary,treasurer,member',
            'sort' => 'nullable|string|in:joined_at,role,status,participation_score',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $page = (int) $request->query('page', 1);
        $cacheKey = 'clubs:show:'.md5(json_encode([
            'club_id' => $club->id,
            'page' => $page,
            'per_page' => $validated['per_page'] ?? 15,
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'role' => $validated['role'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'asc',
        ]));

        [$cachedClub, $members] = Cache::tags(['clubs', 'clubs.show'])->rememberForever($cacheKey, function () use ($club, $validated, $page) {
            // Load club with relationships
            $clubData = $club->fresh()->load([
                'campus',
                'president.student',
            ])->loadCount(['activeMembers', 'pendingApplications', 'officers']);

            // Get paginated members for this club
            $membersData = $club->members()
                ->with(['student', 'approver'])
                ->when($validated['search'] ?? null, function ($query, $search) {
                    $query->whereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where(function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%")
                                ->orWhere('student_id', 'like', "%{$search}%");
                        });
                    });
                })
                ->when($validated['status'] ?? null, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($validated['role'] ?? null, function ($query, $role) {
                    $query->where('role', $role);
                })
                ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                    $direction = $validated['direction'] ?? 'asc';
                    $query->orderBy($sort, $direction);
                })
                ->orderBy('status')
                ->orderBy('role')
                ->orderBy('joined_at')
                ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $page);

            return [$clubData, $membersData];
        });

        return Inertia::render('Clubs/Show', [
            'club' => $cachedClub,
            'members' => $members->withQueryString(),
            'filters' => $request->only(['search', 'status', 'role', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for editing the specified club.
     */
    public function edit(Club $club): Response
    {
        return Inertia::render('Clubs/Edit', [
            'club' => $club->load('campus'),
        ]);
    }

    /**
     * Update the specified club.
     */
    public function update(UpdateClubRequest $request, Club $club): RedirectResponse
    {
        $this->clubService->updateClub($club, $request->validated());

        // Invalidate club caches after update
        Cache::tags(['clubs'])->flush();

        return redirect()->route(ClubRoutes::INDEX)->with('success', 'Club updated successfully.');
    }

    /**
     * Assign a new president to the club.
     */
    public function assignPresident(Club $club, AssignPresidentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->clubService->assignPresident(
            $club,
            $validated['student_id'],
            $validated['assignment_reason'] ?? 'President assigned by administrator'
        );

        // Invalidate club caches after president assignment
        Cache::tags(['clubs'])->flush();

        return redirect()->route(ClubRoutes::SHOW, $club)
            ->with('success', 'President assigned successfully.');
    }

    /**
     * Add a member to the club.
     */
    public function addMember(Club $club, AddClubMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->clubService->addMember(
            $club,
            $validated['student_id'],
            $validated['role'],
            $validated['notes'] ?? null
        );

        // Invalidate club caches after adding member
        Cache::tags(['clubs'])->flush();

        return redirect()->route(ClubRoutes::SHOW, $club)
            ->with('success', 'Member added successfully.');
    }

    /**
     * Get students for president assignment (API endpoint for dropdowns).
     */
    public function studentsForAssignment(Request $request, Club $club)
    {
        $search = (string) ($request->search ?? '');
        $cacheKey = 'clubs:students:'.md5(json_encode([
            'campus_id' => $club->campus_id,
            'search' => $search,
            'limit' => 50,
        ]));

        $students = $this->studentOptions((int) $club->campus_id, $search);

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    /**
     * Get students for club creation (API endpoint for dropdowns).
     */
    public function studentsForCampus(Request $request)
    {
        $campusId = (int) $request->query('campus_id');
        $search = (string) ($request->search ?? '');

        if (! $campusId) {
            return response()->json([
                'success' => false,
                'message' => 'Campus ID is required',
                'data' => [],
            ]);
        }

        $cacheKey = 'clubs:students:campus:'.md5(json_encode([
            'campus_id' => $campusId,
            'search' => $search,
            'limit' => 50,
        ]));

        $students = Cache::tags(['students', 'clubs.students'])->rememberForever(
            $cacheKey,
            fn () => $this->studentOptions($campusId, $search),
        );

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    /** @return list<array{id: int, name: string, student_id: string}> */
    private function studentOptions(int $campusId, string $search): array
    {
        $students = $this->studentReferenceReader->search($search, $campusId, 50);
        $statuses = $this->studentLifecycleStatusReader->statusesFor(array_map(
            static fn ($student): int => $student->id,
            $students,
        ));
        $blockedStatuses = ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'];

        return array_values(array_map(
            static fn ($student): array => [
                'id' => $student->id,
                'name' => $student->fullName,
                'student_id' => $student->studentCode,
            ],
            array_filter(
                $students,
                static fn ($student): bool => ! in_array($statuses[$student->id] ?? null, $blockedStatuses, true),
            ),
        ));
    }
}
