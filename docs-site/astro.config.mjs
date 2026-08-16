import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// Public origin of the published guide. Change this when the domain changes;
// it is used for canonical URLs and the generated sitemap.
const SITE_URL = 'https://docs.knu.edu.vn';

export default defineConfig({
  site: SITE_URL,
  trailingSlash: 'always',
  redirects: {
    '/finance-office/scholarship-adjustments/': '/academic-operations/scholarship-adjustments/',
    '/en/finance-office/scholarship-adjustments/': '/en/academic-operations/scholarship-adjustments/',
    '/ko/finance-office/scholarship-adjustments/': '/ko/academic-operations/scholarship-adjustments/',
    '/zh/finance-office/scholarship-adjustments/': '/zh/academic-operations/scholarship-adjustments/',
    '/faculty-teaching/': '/academic-operations/faculty/',
    '/en/faculty-teaching/': '/en/academic-operations/faculty/',
    '/ko/faculty-teaching/': '/ko/academic-operations/faculty/',
    '/zh/faculty-teaching/': '/zh/academic-operations/faculty/',
    '/campus-operations/': '/campus/',
    '/en/campus-operations/': '/en/campus/',
    '/ko/campus-operations/': '/ko/campus/',
    '/zh/campus-operations/': '/zh/campus/',
  },
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
        zh: { label: '简体中文', lang: 'zh-CN' },
      },
      sidebar: [
        {
          label: 'Bắt đầu',
          translations: { en: 'Getting started', ko: '시작하기', zh: '入门指南' },
          items: [
            {
              label: 'Tổng quan tài liệu',
              translations: { en: 'Overview', ko: '문서 개요', zh: '文档概述' },
              link: '/',
            },
            {
              label: 'Bắt đầu sử dụng',
              translations: { en: 'First steps', ko: '처음 사용하기', zh: '开始使用' },
              slug: 'bat-dau',
            },
          ],
        },
        {
          label: 'Academic Operations',
          translations: { ko: '학사 운영', zh: '学务运营' },
          items: [
            {
              label: 'Tổng quan khu vực',
              translations: { en: 'Area overview', ko: '영역 개요', zh: '区域概述' },
              slug: 'academic-operations',
            },
            {
              label: 'Bản đồ luồng trang',
              translations: { en: 'Page flow map', ko: '화면 흐름도', zh: '页面流程图' },
              slug: 'academic-operations/flow-map',
            },
            { label: 'Curriculum Setup', slug: 'academic-operations/curriculum-setup' },
            { label: 'Course Delivery', slug: 'academic-operations/course-delivery' },
            { label: 'Attendance', slug: 'academic-operations/attendance-completion' },
            { label: 'Grades & Performance', slug: 'academic-operations/grades-performance' },
            { label: 'Faculty', translations: { ko: '교원', zh: '教师' }, slug: 'academic-operations/faculty' },
            {
              label: 'Scholarship Adjustments',
              translations: { ko: '장학금 조정', zh: '奖学金调整' },
              slug: 'academic-operations/scholarship-adjustments',
            },
          ],
        },
        {
          label: 'Finance',
          translations: { ko: '재무처', zh: '财务处' },
          items: [
            {
              label: 'Tổng quan khu vực',
              translations: { en: 'Area overview', ko: '영역 개요', zh: '区域概述' },
              slug: 'finance-office',
            },
          ],
        },
        {
          label: 'Các khu vực khác',
          translations: { en: 'Other areas', ko: '기타 영역', zh: '其他区域' },
          items: [
            { label: 'Students', translations: { ko: '학생', zh: '学生' }, slug: 'student-services' },
            { label: 'Reports & Audits', translations: { ko: '보고서 및 감사', zh: '报表与核对' }, slug: 'reports-audits' },
            { label: 'Store & Clubs', translations: { ko: '상점 및 동아리', zh: '商店与社团' }, slug: 'store-clubs' },
            { label: 'Campus', translations: { ko: '캠퍼스', zh: '校园' }, slug: 'campus' },
            { label: 'Forms & Surveys', translations: { ko: '양식 및 설문', zh: '表单与调查' }, slug: 'forms-quality' },
            { label: 'Communications', translations: { ko: '커뮤니케이션', zh: '通讯' }, slug: 'communications' },
            { label: 'Administration', translations: { ko: '시스템 관리', zh: '系统管理' }, slug: 'administration' },
          ],
        },
      ],
    }),
  ],
});
