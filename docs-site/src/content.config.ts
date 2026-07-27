import { defineCollection } from 'astro:content';
import { docsLoader } from '@astrojs/starlight/loaders';
import { docsSchema } from '@astrojs/starlight/schema';
import { z } from 'astro/zod';

export const collections = {
  docs: defineCollection({
    loader: docsLoader(),
    schema: docsSchema({
      extend: z.object({
        // Repo-relative paths this page documents. When one of these files
        // changes, the page must be reviewed in the same pull request.
        // Enforced by scripts/check-docs-freshness.sh.
        source: z.array(z.string()).optional(),
      }),
    }),
  }),
};
