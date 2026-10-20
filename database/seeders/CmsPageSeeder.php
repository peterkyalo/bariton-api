<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $homeSections = [
            'hero_slides' => [
                [
                    'id' => 1,
                    'badge' => 'Premier Music Conservatory & Masterclasses',
                    'title' => 'The Best Online Music Learning Platform',
                    'subtitle' => 'Master classical piano, contemporary jazz, African gospel, strings, and vocal technique with distinguished virtuoso instructors from Nairobi to the world.',
                    'cta_primary_text' => 'Explore Courses',
                    'cta_primary_link' => '/courses',
                    'cta_secondary_text' => 'Join Now',
                    'cta_secondary_link' => '/register',
                    'image' => 'https://images.unsplash.com/photo-1520523839898-507127b31130?auto=format&fit=crop&w=1600&q=80',
                ],
                [
                    'id' => 2,
                    'badge' => '1-on-1 Mentorship & Residential Bootcamps',
                    'title' => 'Get Educated in Music Online & In Nairobi',
                    'subtitle' => 'Combine flexible online video curriculum with intimate 1-on-1 faculty coaching and immersive multi-day residential masterclasses in Kilimani.',
                    'cta_primary_text' => 'Physical Bootcamps',
                    'cta_primary_link' => '/events',
                    'cta_secondary_text' => 'Book Tutoring',
                    'cta_secondary_link' => '/tutoring',
                    'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1600&q=80',
                ],
                [
                    'id' => 3,
                    'badge' => 'Verifiable Diplomas & Examinations',
                    'title' => 'Earn Internationally Verifiable Credentials',
                    'subtitle' => 'Complete rigorous jury assessments and receive tamper-proof cryptographic certificates that employers, universities, and performance venues can instantly verify.',
                    'cta_primary_text' => 'Verify Certificate',
                    'cta_primary_link' => '/verify',
                    'cta_secondary_text' => 'Course Catalog',
                    'cta_secondary_link' => '/courses',
                    'image' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1600&q=80',
                ],
            ],
            'services' => [
                [
                    'icon' => 'fa-graduation-cap',
                    'title' => 'Skilled Instructors',
                    'description' => 'Learn directly from concert pianists, orchestral cellists, veteran praise directors, and award-winning recording producers.',
                ],
                [
                    'icon' => 'fa-globe',
                    'title' => 'Online Classes',
                    'description' => 'Multi-angle HD lesson streams with step-by-step fingering diagrams, printable sheet music, and practice stems.',
                ],
                [
                    'icon' => 'fa-music',
                    'title' => 'Studio Masterclasses',
                    'description' => 'Intimate physical weekend workshops and residential intensives with live rhythm sections and acoustic auditoriums.',
                ],
                [
                    'icon' => 'fa-certificate',
                    'title' => 'Verifiable Diplomas',
                    'description' => 'Cryptographically signed certificates of mastery and skills validation verifiable anywhere via unique codes.',
                ],
            ],
            'about_block' => [
                'tag' => 'About Us',
                'title' => 'Welcome to Bariton Music Academy',
                'lead' => 'Founded in Nairobi, Kenya, Bariton Music Academy is dedicated to nurturing world-class musical talent across classical disciplines, contemporary African jazz, and sacred gospel traditions.',
                'body' => 'Whether you are a beginner picking up your first instrument or an advanced musician preparing for international conservatory auditions, our modular curriculum and 1-on-1 tutoring ensure steady, measurable artistic growth.',
                'features' => [
                    'Distinguished Conservatory Faculty',
                    'Flexible Online & Residential Paths',
                    'Tamper-Proof Digital Certification',
                    'M-Pesa & International Card Checkout',
                    'Resumable HD Video Submissions',
                    'Dedicated 1-on-1 Mentorship Studios',
                ],
                'image' => 'https://images.unsplash.com/photo-1514119412350-e174d90d280e?auto=format&fit=crop&w=800&q=80',
            ],
            'categories' => [
                [
                    'title' => 'Classical Piano',
                    'count_text' => '12 Modules',
                    'image' => 'https://images.unsplash.com/photo-1520523839898-507127b31130?auto=format&fit=crop&w=600&q=80',
                    'slug' => 'classical-piano',
                ],
                [
                    'title' => 'African Gospel & Jazz',
                    'count_text' => '10 Modules',
                    'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80',
                    'slug' => 'african-gospel-jazz',
                ],
                [
                    'title' => 'Strings & Orchestral Cello',
                    'count_text' => '8 Modules',
                    'image' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=600&q=80',
                    'slug' => 'strings-cello',
                ],
                [
                    'title' => 'Vocal Technique & Production',
                    'count_text' => '9 Modules',
                    'image' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=600&q=80',
                    'slug' => 'vocal-production',
                ],
            ],
            'instructors_spotlight' => [
                [
                    'name' => 'Maestro David Ochieng',
                    'designation' => 'Chair of Piano & Composition',
                    'specialty' => 'Classical Repertoire & Polyphony',
                    'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80',
                ],
                [
                    'name' => 'Dr. Sarah Wanjiku',
                    'designation' => 'Director of Vocal Studies',
                    'specialty' => 'Bel Canto & Contemporary Vocals',
                    'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=400&q=80',
                ],
                [
                    'name' => 'Prof. Brian Otieno',
                    'designation' => 'Principal Strings Instructor',
                    'specialty' => 'Violin, Viola & Chamber Music',
                    'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
                ],
                [
                    'name' => 'Angela Muthoni',
                    'designation' => 'Instructor of Gospel & Jazz Harmony',
                    'specialty' => 'East African Gospel Progressions',
                    'image' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=400&q=80',
                ],
            ],
            'testimonials' => [
                [
                    'name' => 'Grace Mwangi',
                    'role' => 'Conservatory Piano Graduate',
                    'quote' => 'The module progression and rubric feedback gave me the discipline I needed to prepare for international competitions. Having my certificate verifiable online made university admission seamless.',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80',
                ],
                [
                    'name' => 'Emmanuel Kiprotich',
                    'role' => 'Music Director, Nairobi',
                    'quote' => 'The African Gospel Keyboard workshop opened my eyes to passing chords and modulation techniques. The 1-on-1 tutoring sessions with Angela were worth every single shilling.',
                    'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=120&q=80',
                ],
                [
                    'name' => 'Cynthia Ndinda',
                    'role' => 'Vocal Performance Student',
                    'quote' => 'Studying with Dr. Sarah completely eliminated vocal strain and extended my upper register by a major third. The resumable video uploads made submitting assignments so convenient.',
                    'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=120&q=80',
                ],
            ],
        ];

        $pages = [
            [
                'slug' => 'home',
                'title' => 'Home - Bariton Music Academy',
                'category' => 'landing',
                'meta_title' => 'Bariton Music Academy | Premier Conservatory, Courses & Tutoring',
                'meta_description' => 'World-class musical instruction in piano, strings, vocals, African gospel and jazz harmony. Online courses, residential masterclasses, and private 1-on-1 tutoring.',
                'hero_badge' => 'Conservatory Excellence in East Africa',
                'hero_title' => 'The Best Online Music Learning Platform',
                'hero_subtitle' => 'Master classical piano, contemporary jazz, African gospel, strings, and vocal technique with distinguished virtuoso instructors.',
                'hero_cta_text' => 'Explore Courses',
                'hero_cta_link' => '/courses',
                'sections' => $homeSections,
                'is_published' => true,
                'order' => 1,
            ],
            [
                'slug' => 'about',
                'title' => 'About Bariton Music Academy',
                'category' => 'company',
                'meta_title' => 'About Us | Bariton Music Academy Nairobi',
                'meta_description' => 'Learn about Bariton Music Academy, our pedagogical philosophy, conservatory faculty, Kilimani studio facility, and international accreditation standards.',
                'hero_badge' => 'Our Pedagogy & Heritage',
                'hero_title' => 'Empowering Musical Artistry Across Africa',
                'hero_subtitle' => 'Founded to synthesize rigorous Western classical training with vibrant African musical traditions.',
                'content' => "## Our Vision & Mission\n\n**Bariton Music Academy** was established with a singular vision: to cultivate world-class instrumentalists, vocalists, composers, and recording artists across East Africa through uncompromising artistic discipline and accessible digital education.\n\n### Conservatory Facility & Campus\nLocated in the serene artistic quarter of Kilimani, Nairobi (along Argwings Kodhek Road), our campus houses:\n- Acoustic recital auditorium with Steinway & Kawai grand pianos.\n- Soundproof private 1-on-1 coaching studios with multi-camera HD recording rigs.\n- Modern recording and mixing suite equipped with industry standard microphones and monitoring.\n- Sheet music and scores library spanning Baroque, Romantic, East African choral arrangements, and contemporary gospel manuscripts.\n\n### Dual Educational Paths\nWe respect that different musicians have different goals:\n1. **Comprehensive Standard Path**: Step-by-step modular progression with continuous video assignment submissions and instructor rubric reviews.\n2. **Validation-Only Fast Track**: Experienced practitioners can challenge comprehensive examinations directly to validate their mastery and earn recognized credentials.\n\n### International Standards & Verifiable Diplomas\nEvery certificate issued by Bariton Music Academy features an unforgeable cryptographic verification code linked directly to our public portal, ensuring complete transparency for concert presenters and academic institutions worldwide.",
                'is_published' => true,
                'order' => 2,
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'category' => 'company',
                'meta_title' => 'Contact Admissions & Student Services | Bariton Music Academy',
                'meta_description' => 'Get in touch with Bariton Music Academy admissions office, schedule a campus tour in Kilimani, or submit an inquiry.',
                'hero_badge' => 'We Are Here For You',
                'hero_title' => 'Contact Bariton Music Academy',
                'hero_subtitle' => 'Our admissions counselors and student advisors are available Monday through Saturday to answer questions regarding enrollments, masterclasses, and tutoring.',
                'content' => "### Campus & Office Details\n\n- **Physical Address**: BMAS Conservatory Hall, Argwings Kodhek Road, Kilimani, Nairobi, Kenya\n- **General Inquiries**: `admissions@bmas.com`\n- **Finance Directorate**: `finance@bmas.com`\n- **Telephone / WhatsApp**: `+254 712 345 678` / `+254 798 765 432`\n- **Visiting Hours**: Monday – Friday: 8:00 AM – 6:00 PM (EAT) | Saturday: 9:00 AM – 4:00 PM\n\n### Directions & Parking\nFree, secure on-site parking is available for all enrolled students, masterclass attendees, and faculty visitors.",
                'is_published' => true,
                'order' => 3,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms & Conditions of Enrollment',
                'category' => 'legal',
                'meta_title' => 'Terms and Conditions | Bariton Music Academy',
                'meta_description' => 'Official terms of enrollment, course access, assignment submissions, refund policies, and intellectual property protections.',
                'hero_badge' => 'Enrollment Agreement',
                'hero_title' => 'Terms & Conditions',
                'hero_subtitle' => 'Please read our terms and conditions governing course enrollments, masterclass seats, tutoring credits, and digital diplomas.',
                'content' => "## 1. Enrollment & Access\nEnrollment in digital courses provides perpetual personal access to curriculum videos, downloadable scores, and assignment portals. Credentials may not be shared across individuals.\n\n## 2. Payments & Tax Receipts\nAll prices are quoted in Kenya Shillings (KES) inclusive of 16% VAT where applicable. Sequential electronic receipts (BMAS-RCT) are generated instantly upon M-Pesa or card fulfillment.\n\n## 3. Physical Event Seat Holds & Cancellations\n- Online event seat holds expire automatically after 15 minutes if payment is not initiated.\n- Cash registration holds at the finance desk expire after 48 hours.\n- Refund requests for physical bootcamps submitted 7 or more days prior to event start are eligible for full refunds less 5% administrative fee.\n\n## 4. 1-on-1 Tutoring Cancellations & Rescheduling\n- Students may reschedule a booked private tutoring session at least 24 hours prior to scheduled start time without fee or credit loss.\n- Emergency rescheduling within 24 hours requires a documented reason.\n- Instructor cancellations immediately restore 100% of consumed credits back to the student account.\n\n## 5. Certification & Academic Integrity\nPlagiarism, proxy performance submissions, or falsified recording submissions will result in immediate exam disqualification and revocation of any associated credentials.",
                'is_published' => true,
                'order' => 4,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'category' => 'legal',
                'meta_title' => 'Privacy Policy | Bariton Music Academy',
                'meta_description' => 'How Bariton Music Academy handles student personal data, performance recordings, and guardian consents under Kenyan data protection laws.',
                'hero_badge' => 'Data Protection & Trust',
                'hero_title' => 'Privacy Policy',
                'hero_subtitle' => 'We are committed to safeguarding student data, performance recordings, and payment information.',
                'content' => "## Data Protection Compliance\nBariton Music Academy processes student personal data in accordance with the **Kenya Data Protection Act, 2019**.\n\n### Data We Collect\n- Account Information: Name, email address, phone number, and date of birth.\n- Academic Submissions: Video and audio recordings submitted for grading and jury review.\n- Transaction Records: Payment gateway references, billing details, and receipt numbers.\n\n### Minor Students & Guardian Consent\nFor students under 18 years of age, explicit parental or legal guardian consent is verified via automated tokenized guardian links prior to granting interactive platform access.\n\n### Video Submission Retention\nStudent assignment videos are stored on secured, encrypted storage disks and streamed strictly through short-lived signed URLs. Performance videos are never shared publicly without explicit written student permission.",
                'is_published' => true,
                'order' => 5,
            ],
        ];

        foreach ($pages as $p) {
            CmsPage::updateOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, ['uuid' => (string) Str::uuid()])
            );
        }
    }
}
