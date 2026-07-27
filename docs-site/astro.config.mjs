import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// Public origin of the published guide. Change this when the domain changes;
// it is used for canonical URLs and the generated sitemap.
const SITE_URL = 'https://docs.knu.edu.vn';

export default defineConfig({
  site: SITE_URL,
  trailingSlash: 'always',
  integrations: [
    starlight({
      title: 'Portal User Guide',
      description: 'Tài liệu hướng dẫn sử dụng Portal cho đội vận hành học vụ.',
      customCss: ['./src/styles/custom.css'],
      pagefind: true,
      // Vietnamese is served from the root so its published URLs stay stable.
      // A locale with no translated page falls back to the Vietnamese one.
      defaultLocale: 'root',
      locales: {
        root: { label: 'Tiếng Việt', lang: 'vi' },
        en: { label: 'English', lang: 'en' },
        ko: { label: '한국어', lang: 'ko' },
      },
      sidebar: [
        {
          label: 'Bắt đầu',
          translations: { en: 'Getting started', ko: '시작하기' },
          items: [
            {
              label: 'Tổng quan tài liệu',
              translations: { en: 'Overview', ko: '문서 개요' },
              link: '/',
            },
            {
              label: 'Bắt đầu sử dụng',
              translations: { en: 'First steps', ko: '처음 사용하기' },
              slug: 'bat-dau',
            },
          ],
        },
        {
          label: 'Academic Operations',
          translations: { ko: '학사 운영' },
          items: [
            {
              label: 'Tổng quan khu vực',
              translations: { en: 'Area overview', ko: '영역 개요' },
              slug: 'academic-operations',
            },
            {
              label: 'Bản đồ luồng trang',
              translations: { en: 'Page flow map', ko: '화면 흐름도' },
              slug: 'academic-operations/flow-map',
            },
            { label: 'Curriculum Setup', slug: 'academic-operations/curriculum-setup' },
            { label: 'Course Delivery', slug: 'academic-operations/course-delivery' },
            { label: 'Attendance & Completion', slug: 'academic-operations/attendance-completion' },
            { label: 'Grades & Performance', slug: 'academic-operations/grades-performance' },
          ],
        },
        {
          label: 'Các khu vực khác',
          translations: { en: 'Other areas', ko: '기타 영역' },
          items: [
            { label: 'Student Services', translations: { ko: '학생 서비스' }, slug: 'student-services' },
            { label: 'Reports & Audits', translations: { ko: '보고서 및 감사' }, slug: 'reports-audits' },
            { label: 'Faculty & Teaching', translations: { ko: '교원 및 강의' }, slug: 'faculty-teaching' },
            { label: 'Finance Office', translations: { ko: '재무처' }, slug: 'finance-office' },
            { label: 'Forms & Quality', translations: { ko: '양식 및 품질' }, slug: 'forms-quality' },
            { label: 'Campus Operations', translations: { ko: '캠퍼스 운영' }, slug: 'campus-operations' },
            { label: 'Communications', translations: { ko: '커뮤니케이션' }, slug: 'communications' },
            { label: 'Administration', translations: { ko: '시스템 관리' }, slug: 'administration' },
          ],
        },
      ],
    }),
  ],
});
