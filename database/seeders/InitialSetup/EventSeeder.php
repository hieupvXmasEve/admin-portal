<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;
use App\Modules\Engagement\Models\Event;
use App\Modules\Engagement\Models\EventParticipant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates various types of events across different campuses
     */
    public function run(): void
    {
        $this->command->info('📅 Creating events...');

        // Clean existing data
        $this->cleanExistingData();

        // Create events
        $this->createEvents();

        $this->command->info('✅ Events created successfully!');
    }

    private function cleanExistingData(): void
    {
        EventParticipant::query()->delete();
        Event::query()->delete();
        $this->command->info('🧹 Cleaned existing event data');
    }

    private function createEvents(): void
    {
        $campuses = Campus::all();
        $users = User::all();
        $lecturers = Lecture::all();

        if ($campuses->isEmpty() || $users->isEmpty()) {
            $this->command->warn('⚠️  No campuses or users found. Please run previous seeders first.');
            return;
        }

        $events = [
            // Academic Events
            [
                'title' => 'Orientation Day 2025',
                'description' => 'Welcome new students to our campus! Join us for campus tours, meet faculty members, and learn about student services.',
                'start_time' => Carbon::now()->addDays(7)->setTime(9, 0),
                'end_time' => Carbon::now()->addDays(7)->setTime(17, 0),
                'location' => 'Main Auditorium',
                'gold_reward_amount' => 50.00,
                'max_participants' => 200,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'IT Career Fair 2025',
                'description' => 'Connect with leading IT companies and explore career opportunities in technology. Bring your resume!',
                'start_time' => Carbon::now()->addDays(14)->setTime(10, 0),
                'end_time' => Carbon::now()->addDays(14)->setTime(16, 0),
                'location' => 'Exhibition Hall',
                'gold_reward_amount' => 75.00,
                'max_participants' => 150,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'Programming Workshop: Web Development',
                'description' => 'Hands-on workshop covering modern web development technologies including React, Node.js, and databases.',
                'start_time' => Carbon::now()->addDays(21)->setTime(13, 0),
                'end_time' => Carbon::now()->addDays(21)->setTime(17, 0),
                'location' => 'Computer Lab A',
                'gold_reward_amount' => 100.00,
                'max_participants' => 30,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'Data Science Seminar',
                'description' => 'Learn about the latest trends in data science, machine learning, and AI applications in industry.',
                'start_time' => Carbon::now()->addDays(28)->setTime(14, 0),
                'end_time' => Carbon::now()->addDays(28)->setTime(16, 0),
                'location' => 'Conference Room B',
                'gold_reward_amount' => 60.00,
                'max_participants' => 50,
                'organizer_type' => 'school',
                'status' => 'published',
            ],

            // Social Events
            [
                'title' => 'Student Club Fair',
                'description' => 'Discover various student clubs and organizations. Sign up for clubs that interest you!',
                'start_time' => Carbon::now()->addDays(10)->setTime(11, 0),
                'end_time' => Carbon::now()->addDays(10)->setTime(15, 0),
                'location' => 'Student Center',
                'gold_reward_amount' => 25.00,
                'max_participants' => 100,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'Cultural Night',
                'description' => 'Celebrate diversity with performances, food, and cultural displays from different countries.',
                'start_time' => Carbon::now()->addDays(35)->setTime(18, 0),
                'end_time' => Carbon::now()->addDays(35)->setTime(22, 0),
                'location' => 'Cultural Center',
                'gold_reward_amount' => 40.00,
                'max_participants' => 120,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'Sports Tournament: Basketball',
                'description' => 'Inter-campus basketball tournament. Teams of 5 players. Registration required.',
                'start_time' => Carbon::now()->addDays(42)->setTime(9, 0),
                'end_time' => Carbon::now()->addDays(42)->setTime(18, 0),
                'location' => 'Sports Complex',
                'gold_reward_amount' => 80.00,
                'max_participants' => 50,
                'organizer_type' => 'school',
                'status' => 'published',
            ],

            // Professional Development
            [
                'title' => 'Resume Writing Workshop',
                'description' => 'Learn how to create an effective resume and cover letter for IT positions.',
                'start_time' => Carbon::now()->addDays(17)->setTime(14, 0),
                'end_time' => Carbon::now()->addDays(17)->setTime(16, 0),
                'location' => 'Career Services Office',
                'gold_reward_amount' => 35.00,
                'max_participants' => 25,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'Interview Skills Training',
                'description' => 'Practice technical and behavioral interview questions with industry professionals.',
                'start_time' => Carbon::now()->addDays(24)->setTime(10, 0),
                'end_time' => Carbon::now()->addDays(24)->setTime(12, 0),
                'location' => 'Interview Room',
                'gold_reward_amount' => 45.00,
                'max_participants' => 20,
                'organizer_type' => 'school',
                'status' => 'published',
            ],

            // Technology Events
            [
                'title' => 'Hackathon 2025',
                'description' => '48-hour coding competition. Build innovative solutions and win prizes!',
                'start_time' => Carbon::now()->addDays(45)->setTime(9, 0),
                'end_time' => Carbon::now()->addDays(47)->setTime(17, 0),
                'location' => 'Innovation Lab',
                'gold_reward_amount' => 200.00,
                'max_participants' => 60,
                'organizer_type' => 'school',
                'status' => 'published',
            ],
            [
                'title' => 'AI & Machine Learning Workshop',
                'description' => 'Introduction to AI concepts and hands-on machine learning projects.',
                'start_time' => Carbon::now()->addDays(31)->setTime(9, 0),
                'end_time' => Carbon::now()->addDays(31)->setTime(17, 0),
                'location' => 'AI Lab',
                'gold_reward_amount' => 120.00,
                'max_participants' => 40,
                'organizer_type' => 'school',
                'status' => 'published',
            ],

            // Past Events (for testing)
            [
                'title' => 'Welcome Back Party',
                'description' => 'Welcome back party for returning students with music, food, and games.',
                'start_time' => Carbon::now()->subDays(7)->setTime(18, 0),
                'end_time' => Carbon::now()->subDays(7)->setTime(22, 0),
                'location' => 'Student Lounge',
                'gold_reward_amount' => 30.00,
                'max_participants' => 80,
                'organizer_type' => 'school',
                'status' => 'completed',
            ],
            [
                'title' => 'Tech Talk: Future of Programming',
                'description' => 'Industry expert discusses emerging programming languages and technologies.',
                'start_time' => Carbon::now()->subDays(3)->setTime(15, 0),
                'end_time' => Carbon::now()->subDays(3)->setTime(17, 0),
                'location' => 'Lecture Hall 1',
                'gold_reward_amount' => 55.00,
                'max_participants' => 60,
                'organizer_type' => 'school',
                'status' => 'completed',
            ],
        ];

        foreach ($events as $eventData) {
            $campus = $campuses->random();
            $creator = $this->getCreator($users, $lecturers);

            $event = Event::create([
                'campus_id' => $campus->id,
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'start_time' => $eventData['start_time'],
                'end_time' => $eventData['end_time'],
                'location' => $eventData['location'],
                'gold_reward_amount' => $eventData['gold_reward_amount'],
                'max_participants' => $eventData['max_participants'],
                'qr_code' => $this->generateQRCode(),
                'organizer_type' => 'school',
                'organizer_id' => $campus->id, // For school events, organizer_id is campus_id
                'status' => $eventData['status'],
                'created_by_user_id' => $creator->id,
            ]);

            // Create some participants for published/completed events
            if (in_array($event->status, ['published', 'completed'])) {
                $this->createEventParticipants($event);
            }

            $this->command->info("📅 Created event: {$event->title} at {$campus->name}");
        }

        $this->command->info('🎉 Created ' . count($events) . ' events across ' . $campuses->count() . ' campuses');
    }

    private function getCreator($users, $lecturers): User
    {
        if ($lecturers->isNotEmpty()) {
            return $lecturers->random()->user;
        }

        return $users->random();
    }

    private function generateQRCode(): string
    {
        return 'EVENT_' . strtoupper(uniqid());
    }

    private function createEventParticipants(Event $event): void
    {
        $students = Student::all();

        if ($students->isEmpty()) {
            return;
        }

        // Create participants for 30-70% of max capacity
        $participantCount = rand(
            (int) ($event->max_participants * 0.3),
            (int) ($event->max_participants * 0.7)
        );

        $selectedStudents = $students->random(min($participantCount, $students->count()));

        foreach ($selectedStudents as $student) {
            $status = $this->getRandomParticipantStatus($event);

            EventParticipant::create([
                'event_id' => $event->id,
                'student_id' => $student->id,
                'status' => $status,
                'registered_at' => $event->start_time->subDays(rand(1, 14)),
                'checkin_time' => $status === 'checked_in' || $status === 'completed'
                    ? $event->start_time->addMinutes(rand(0, 30))
                    : null,
                'gold_awarded' => $status === 'completed',
                'awarded_at' => $status === 'completed'
                    ? $event->end_time->subMinutes(rand(0, 60))
                    : null,
            ]);
        }

        $this->command->info("👥 Created {$selectedStudents->count()} participants for {$event->title}");
    }

    private function getRandomParticipantStatus(Event $event): string
    {
        $statuses = ['registered', 'checked_in', 'completed', 'cancelled'];
        $weights = [20, 30, 40, 10]; // More likely to be completed/checked in

        // For past events, increase completed status probability
        if ($event->end_time->isPast()) {
            $weights = [5, 15, 70, 10];
        }

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $index => $status) {
            $cumulative += $weights[$index];
            if ($random <= $cumulative) {
                return $status;
            }
        }

        return 'registered';
    }
}
