import assert from "node:assert/strict";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { spawnSync } from "node:child_process";
import test from "node:test";

const workflow = readFileSync(
  new URL("../.github/workflows/dependabot-auto-merge.yml", import.meta.url),
  "utf8",
);
const script = workflow
  .split("        run: |\n")[1]
  .split("\n")
  .map((line) => line.replace(/^          /, ""))
  .join("\n");
const head = "a".repeat(40);

function runWorkflow(t, overrides = {}) {
  const directory = mkdtempSync(join(tmpdir(), "dependabot-workflow-"));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const log = join(directory, "calls.jsonl");
  writeFileSync(log, "");
  writeFileSync(
    join(directory, "gh"),
    `#!/usr/bin/env node
const fs = require("node:fs");
const args = process.argv.slice(2);
fs.appendFileSync(process.env.MOCK_LOG, JSON.stringify(args) + "\\n");
if (args[0] === "api" && args[1] === "--paginate") {
  console.log(args[2].endsWith("/commits") ? process.env.MOCK_COMMITS : process.env.MOCK_FILES);
} else if (args[0] === "pr" && args[1] === "view") {
  const checked = fs.readFileSync(process.env.MOCK_LOG, "utf8").includes('["pr","checks",');
  console.log(checked && process.env.MOCK_HEAD_AFTER_CHECKS ? process.env.MOCK_HEAD_AFTER_CHECKS : process.env.MOCK_HEAD);
} else if (args[0] === "pr" && args[1] === "checks") {
  process.exit(Number(process.env.MOCK_CHECK_EXIT || 0));
} else if (args[0] === "api" && args[1] === "--method") {
  process.exit(Number(process.env.MOCK_REVIEW_EXIT || 0));
} else if (args[0] === "pr" && args[1] === "merge") {
  process.exit(Number(process.env.MOCK_MERGE_EXIT || 0));
} else if (args[0] !== "workflow" || args[1] !== "run") {
  process.exit(99);
}
`,
    { mode: 0o755 },
  );
  const result = spawnSync("bash", ["-e", "-o", "pipefail", "-c", script], {
    encoding: "utf8",
    env: {
      ...process.env,
      PATH: `${directory}:${process.env.PATH}`,
      GITHUB_REPOSITORY: "example/contact-manager",
      PR_NUMBER: "123",
      PR_URL: "https://github.com/example/contact-manager/pull/123",
      PR_HEAD_SHA: head,
      UPDATE_ECOSYSTEM: "npm_and_yarn",
      UPDATE_TYPE: "version-update:semver-patch",
      MAINTAINER_CHANGES: "false",
      MOCK_HEAD: head,
      MOCK_COMMITS: head,
      MOCK_FILES:
        "composer.json\ncomposer.lock\npackage.json\npackage-lock.json",
      MOCK_LOG: log,
      ...overrides,
    },
  });
  const calls = readFileSync(log, "utf8")
    .trim()
    .split("\n")
    .filter(Boolean)
    .map((line) => JSON.parse(line));
  return { ...result, calls };
}

test("only passing checks permit approval, exact-head merge and main CI dispatch", (t) => {
  const result = runWorkflow(t);
  assert.equal(result.status, 0, result.stderr);
  const checks = result.calls.findIndex((args) => args[1] === "checks");
  const review = result.calls.findIndex((args) =>
    args.includes("event=APPROVE"),
  );
  const merge = result.calls.findIndex((args) => args[1] === "merge");
  assert.ok(checks >= 0 && checks < review && review < merge);
  assert.ok(result.calls[review].includes(`commit_id=${head}`));
  assert.deepEqual(result.calls[merge].slice(-2), [
    "--match-head-commit",
    head,
  ]);
  assert.deepEqual(result.calls.at(-1), [
    "workflow",
    "run",
    "ci.yml",
    "--repo",
    "example/contact-manager",
    "--ref",
    "main",
  ]);
});

for (const files of [
  "src/AuthController.php",
  "package-lock.json\n.github/workflows/ci.yml",
]) {
  test(`changes to ${files} require manual review`, (t) => {
    const result = runWorkflow(t, { MOCK_FILES: files });
    assert.equal(result.status, 0);
    assert.ok(!result.calls.some((args) => args[1] === "checks"));
  });
}

for (const overrides of [
  { MOCK_FILES: "" },
  { MOCK_HEAD: "b".repeat(40) },
  { MOCK_HEAD_AFTER_CHECKS: "b".repeat(40) },
  { MOCK_CHECK_EXIT: "1" },
  { MOCK_COMMITS: `${head}\n${"b".repeat(40)}` },
  { MOCK_COMMITS: "b".repeat(40) },
]) {
  test(`unsafe or unverified candidate cannot be approved: ${JSON.stringify(overrides)}`, (t) => {
    const result = runWorkflow(t, overrides);
    assert.notEqual(result.status, 0);
    assert.ok(!result.calls.some((args) => args.includes("event=APPROVE")));
    assert.ok(!result.calls.some((args) => args[1] === "merge"));
  });
}

for (const overrides of [
  { UPDATE_ECOSYSTEM: "github_actions" },
  { UPDATE_TYPE: "version-update:semver-major" },
  { UPDATE_TYPE: "" },
  { MAINTAINER_CHANGES: "true" },
  { MAINTAINER_CHANGES: "" },
]) {
  test(`ineligible update remains manual: ${JSON.stringify(overrides)}`, (t) => {
    const result = runWorkflow(t, overrides);
    assert.equal(result.status, 0);
    assert.equal(result.calls.length, 0);
  });
}

test("Composer minor updates are eligible", (t) => {
  const result = runWorkflow(t, {
    UPDATE_ECOSYSTEM: "composer",
    UPDATE_TYPE: "version-update:semver-minor",
  });
  assert.equal(result.status, 0, result.stderr);
  assert.ok(result.calls.some((args) => args[1] === "merge"));
});

test("rejected approval prevents merging", (t) => {
  const result = runWorkflow(t, { MOCK_REVIEW_EXIT: "1" });
  assert.notEqual(result.status, 0);
  assert.ok(!result.calls.some((args) => args[1] === "merge"));
});

test("failed merge cannot dispatch production CI", (t) => {
  const result = runWorkflow(t, { MOCK_MERGE_EXIT: "1" });
  assert.notEqual(result.status, 0);
  assert.ok(!result.calls.some((args) => args[0] === "workflow"));
});
