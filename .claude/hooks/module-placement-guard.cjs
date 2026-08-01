#!/usr/bin/env node
/**
 * PreToolUse hook — denies creating a NEW controller/request under the global
 * app/Http tree when a feature module already owns that feature name.
 *
 * Why this exists: this repo separates code by feature module
 * (app/Modules/<Owner>/...). A feature's HTTP layer must live under the SAME
 * owner module as its model/query/policy. The recurring mistake is anchoring on
 * a same-named file that happens to sit in the global app/Http tree and copying
 * that location, instead of resolving the owner module first.
 *
 * Scope is deliberately narrow to stay false-positive-free:
 *   - only Write (creating), never Edit
 *   - only files that do NOT already exist (pre-existing globals stay put)
 *   - only app/Http/Controllers/** and app/Http/Requests/**
 *   - only when a same-stem file already exists somewhere under app/Modules/
 *
 * Always fail-open: allows the operation on any error.
 */

'use strict';

const { createHookTimer, logHookCrash } = require('./lib/hook-logger.cjs');

// Suffixes/prefixes stripped to get the feature stem from a class filename.
// e.g. PreviewScholarshipAdjustmentCandidatesRequest -> ScholarshipAdjustment
const CLASS_SUFFIXES = /(Controller|Request|FormRequest)$/;
const ACTION_PREFIXES =
  /^(Store|Update|Create|Delete|Destroy|Identify|Preview|Add|Approve|Confirm|Decide|Schedule|Complete|Edit|Record|Mark|Reject|Generate|List|Get|Index|Show|Import|Export|Bulk|Assign|Restore|Cancel)/;

// Plural/collection tails stripped so "Candidates"/"Dossiers" still match the
// module files that use the singular form.
const TRAILING_NOUNS = /(Candidates|Candidate|Dossiers|Dossier|Items|Item|List)$/;

const MIN_STEM_LENGTH = 8;

function featureStem(basename) {
  let stem = basename.replace(/\.php$/, '');
  stem = stem.replace(CLASS_SUFFIXES, '');
  stem = stem.replace(ACTION_PREFIXES, '');
  stem = stem.replace(TRAILING_NOUNS, '');
  return stem;
}

/** Collect every .php filename under app/Modules, mapped to its directory. */
function collectModuleFiles(fs, path, root) {
  const results = [];
  const stack = [root];

  while (stack.length > 0) {
    const dir = stack.pop();
    let entries;
    try {
      entries = fs.readdirSync(dir, { withFileTypes: true });
    } catch (_) {
      continue;
    }

    for (const entry of entries) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        stack.push(full);
      } else if (entry.name.endsWith('.php')) {
        results.push(full);
      }
    }
  }

  return results;
}

// Directory names that are LAYERS inside a module, not sub-modules. A second
// path segment matching one of these means the owner is just <Module>.
const LAYER_DIRS = new Set([
  'Actions',
  'Console',
  'Contracts',
  'Data',
  'Enums',
  'Events',
  'Exceptions',
  'Http',
  'Jobs',
  'Listeners',
  'Models',
  'Observers',
  'Policies',
  'Providers',
  'Queries',
  'Rules',
  'Services',
  'Support',
  'routes',
]);

/** Owner module path, e.g. app/Modules/Academic/Progression. */
function ownerOf(modulePath, root) {
  const relative = modulePath.slice(root.length).replace(/^[/\\]/, '').replace(/\\/g, '/');
  const parts = relative.split('/');
  // Owner = <Module>[/<SubModule>]; a layer directory ends the owner path.
  const owner = [parts[0]];
  if (parts[1] && /^[A-Z]/.test(parts[1]) && !parts[1].endsWith('.php') && !LAYER_DIRS.has(parts[1])) {
    owner.push(parts[1]);
  }
  return 'app/Modules/' + owner.join('/');
}

(async () => {
  const timer = createHookTimer('module-placement-guard', { event: 'PreToolUse', tool: 'Write' });

  const allow = () => {
    process.stdout.write(JSON.stringify({ continue: true }));
    process.exit(0);
  };

  try {
    const { isHookEnabled } = require('./lib/ck-config-utils.cjs');
    if (!isHookEnabled('module-placement-guard')) {
      timer.end({ status: 'skip', exit: 0, note: 'disabled' });
      allow();
    }

    let raw = '';
    for await (const chunk of process.stdin) {
      raw += chunk;
    }

    const data = JSON.parse(raw);
    const filePath = data.tool_input?.file_path || data.tool_input?.path || '';
    const normalized = filePath.replace(/\\/g, '/');

    const inGlobalHttp =
      /\/app\/Http\/Controllers\//.test(normalized) || /\/app\/Http\/Requests\//.test(normalized);

    if (!normalized.endsWith('.php') || !inGlobalHttp) {
      timer.end({ status: 'skip', exit: 0, note: 'out-of-scope' });
      allow();
    }

    const fs = require('fs');
    const path = require('path');

    // Editing/overwriting an existing global file is out of scope — the rule
    // governs NEW files only.
    if (fs.existsSync(filePath)) {
      timer.end({ status: 'skip', exit: 0, note: 'existing-file' });
      allow();
    }

    const projectDir = process.env.CLAUDE_PROJECT_DIR || process.cwd();
    const modulesRoot = path.join(projectDir, 'app', 'Modules');

    if (!fs.existsSync(modulesRoot)) {
      timer.end({ status: 'skip', exit: 0, note: 'no-modules-dir' });
      allow();
    }

    const stem = featureStem(path.basename(normalized));

    if (stem.length < MIN_STEM_LENGTH) {
      timer.end({ status: 'skip', exit: 0, note: 'stem-too-short' });
      allow();
    }

    const moduleFiles = collectModuleFiles(fs, path, modulesRoot);
    const owners = new Set();

    for (const modulePath of moduleFiles) {
      if (path.basename(modulePath).includes(stem)) {
        owners.add(ownerOf(modulePath, modulesRoot));
      }
    }

    if (owners.size === 0) {
      timer.end({ status: 'ok', exit: 0, note: 'no-owner-module' });
      allow();
    }

    const ownerList = Array.from(owners).sort();
    const reason = [
      `Feature "${stem}" is already owned by a module: ${ownerList.join(', ')}.`,
      '',
      'This repo keeps a feature\'s whole HTTP layer inside its owner module, not',
      'in the global app/Http tree. Place this file under the owner module using',
      'that module\'s own convention for this file TYPE, e.g.:',
      `  ${ownerList[0]}/Http/Web/<Name>Controller.php`,
      `  ${ownerList[0]}/Http/Api/...`,
      `  ${ownerList[0]}/Http/Requests/<Feature>/<Name>Request.php`,
      '',
      'Register its route in the owner module\'s own route file',
      '(app/Modules/<Owner>/routes/web.php), not the global routes/web/* shells.',
      '',
      'Check the owner module first for how it already names and nests this file',
      'type — do not copy the location of a same-named file elsewhere.',
    ].join('\n');

    timer.end({ status: 'deny', exit: 0, target: normalized, note: ownerList.join(',') });
    process.stdout.write(
      JSON.stringify({
        hookSpecificOutput: {
          hookEventName: 'PreToolUse',
          permissionDecision: 'deny',
          permissionDecisionReason: reason,
        },
      }),
    );
    process.exit(0);
  } catch (err) {
    try {
      logHookCrash('module-placement-guard', err, { event: 'PreToolUse', tool: 'Write' });
    } catch (_) {}
    // Fail-open
    process.stdout.write(JSON.stringify({ continue: true }));
    process.exit(0);
  }
})();
