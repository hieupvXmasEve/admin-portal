#!/usr/bin/env node
/**
 * PostToolUse hook — flags Inertia form submits in Vue pages that give the user
 * no visible feedback.
 *
 * Why this exists: this app's controllers flash via `back()->with('success')`,
 * which lands in `page.props.flash` (session-based). The global bridge in
 * resources/js/composables/useFlashToast.ts only listens to Inertia v3's NATIVE
 * flash event, so it does NOT pick those up. A page that posts without an
 * onSuccess/onError handler therefore succeeds silently — the user clicks and
 * nothing appears to happen.
 *
 * Always fail-open: returns { continue: true } on any error.
 */

'use strict';

const { createHookTimer, logHookCrash } = require('./lib/hook-logger.cjs');

// Inertia visit verbs that mutate and therefore carry a flash response.
// Deliberately anchored to an Inertia receiver (`router` or a *Form object) so
// axios calls like `api.post(...)` and DOM calls like
// `url.searchParams.delete(...)` do not trip this.
const SUBMIT_CALLS = [
  /(^|[^\w.])(router|[A-Za-z_$][\w$]*[Ff]orm)\s*\.\s*(post|put|patch|delete)\s*\(/,
  /useForm\([^)]*\)\s*\.\s*(post|put|patch|delete)\s*\(/,
];

// Any of these proves the page handles the response itself. Rendering
// props.errors is deliberately NOT enough: that only covers the failure path,
// while a flashed success still passes silently.
const FEEDBACK_MARKERS = ['onSuccess', 'onError', 'onFinish', 'props.flash'];

let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  input += chunk;
});
process.stdin.on('end', () => {
  const timer = createHookTimer('vue-inertia-feedback', { event: 'PostToolUse' });
  try {
    const { isHookEnabled } = require('./lib/ck-config-utils.cjs');
    if (!isHookEnabled('vue-inertia-feedback')) {
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const data = JSON.parse(input);
    const toolName = data.tool_name || '';
    const filePath = data.tool_input?.file_path || data.tool_input?.path || '';

    // Resolve first: the tool may hand us a project-relative path, which would
    // not match a leading-slash directory test.
    const path = require('path');
    const projectDir = process.env.CLAUDE_PROJECT_DIR || process.cwd();
    const absolute = path.isAbsolute(filePath) ? filePath : path.join(projectDir, filePath);
    const normalized = absolute.replace(/\\/g, '/');
    const isVuePage = normalized.endsWith('.vue') && normalized.includes('/resources/js/');

    if (!isVuePage) {
      timer.end({ tool: toolName, status: 'skip', exit: 0, note: 'not-vue-page' });
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const fs = require('fs');
    if (!fs.existsSync(absolute)) {
      timer.end({ tool: toolName, status: 'skip', exit: 0, note: 'file-missing' });
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const content = fs.readFileSync(absolute, "utf8");

    if (!SUBMIT_CALLS.some((pattern) => pattern.test(content))) {
      timer.end({ tool: toolName, status: 'ok', exit: 0, note: 'no-submit' });
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const hasFeedback = FEEDBACK_MARKERS.some((marker) => content.includes(marker));

    if (hasFeedback) {
      timer.end({ tool: toolName, status: 'ok', exit: 0 });
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const warning = [
      '[!] Inertia submit with no user feedback: ' + normalized.split('/resources/js/').pop(),
      '    This app flashes via back()->with(...) => page.props.flash (session-based).',
      '    useFlashToast.ts only bridges Inertia NATIVE flash, so it will NOT toast these.',
      '    Add to the visit options:',
      "      onSuccess: (page) => { const m = page.props.flash?.success; if (m) toast.success(m); },",
      "      onError: (errors) => toast.error(errors.error ?? 'Failed'),",
      "    Import: import { toast } from 'vue-sonner';",
      '    Reference: resources/js/pages/ClassSessions/Show.vue',
    ].join('\n');

    timer.end({ tool: toolName, status: 'warn', exit: 0, target: normalized });
    process.stdout.write(JSON.stringify({ continue: true, additionalContext: warning }));
  } catch (err) {
    logHookCrash('vue-inertia-feedback', err, { event: 'PostToolUse' });
    process.stdout.write(JSON.stringify({ continue: true }));
  }
});
