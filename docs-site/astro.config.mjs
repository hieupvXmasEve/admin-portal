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
      title: 'Swinx User Guide',
      description: 'Tài liệu hướng dẫn sử dụng Swinx cho đội vận hành học vụ.',
      customCss: ['./src/styles/custom.css'],
      pagefind: false,
      sidebar: [
        {
          label: 'Bắt đầu',
          items: [
            { label: 'Tổng quan tài liệu', link: '/' },
            { label: 'Bắt đầu sử dụng', slug: 'bat-dau' },
          ],
        },
        {
          label: 'Academic Operations',
          items: [
            { label: 'Tổng quan khu vực', slug: 'academic-operations' },
            { label: 'Bản đồ luồng trang', slug: 'academic-operations/flow-map' },
            { label: 'Curriculum Setup', slug: 'academic-operations/curriculum-setup' },
            { label: 'Course Delivery', slug: 'academic-operations/course-delivery' },
            { label: 'Attendance & Completion', slug: 'academic-operations/attendance-completion' },
            { label: 'Grades & Performance', slug: 'academic-operations/grades-performance' },
          ],
        },
      ],
    }),
  ],
});
