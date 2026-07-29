#!/usr/bin/env node
import { program } from "commander";
import chalk from "chalk";
import ora from "ora";

const API_BASE = process.env.API_URL || "http://localhost:8000";

program
  .name("run-phases")
  .description("Execute AI Planning Studio phases in sequence (vibe coding mode)")
  .requiredOption("--token <token>", "Project API token")
  .option("--api <url>", "API base URL", API_BASE)
  .option("--version-id <id>", "Version ID (if not provided, fetch latest)")
  .option("--project-id <id>", "Project ID (used with --version-id or to find latest)")
  .option("--from <phase>", "Start from specific phase key", "")
  .option("--ai-provider <provider>", "AI provider: openai|anthropic|custom", "openai")
  .option("--ai-key <key>", "AI API key (or set AI_API_KEY env)")
  .option("--ai-model <model>", "AI model", "gpt-4")
  .option("--ai-base-url <url>", "AI base URL for custom provider")
  .option("--auto", "Auto-confirm all prompts (default: true)", true)
  .parse();

const opts = program.opts();
const TOKEN = opts.token;
const AUTH_HEADERS = { Authorization: `Bearer ${TOKEN}`, "Content-Type": "application/json" };

function formatPhaseTitle(phase) {
  const done = phase.done ? chalk.green("✅") : chalk.yellow("⏳");
  return `${done} ${chalk.bold(phase.title)}`;
}

function drawProgressBar(current, total) {
  const width = 30;
  const filled = Math.round((current / total) * width);
  const bar = "█".repeat(filled) + "░".repeat(width - filled);
  return chalk.cyan(`[${bar}] ${current}/${total}`);
}

async function apiGet(path) {
  const res = await fetch(`${opts.api || API_BASE}/api${path}`, { headers: AUTH_HEADERS });
  if (!res.ok) {
    const body = await res.text();
    throw new Error(`GET ${path} ${res.status}: ${body.substring(0, 200)}`);
  }
  return res.json();
}

async function apiPost(path, body) {
  const res = await fetch(`${opts.api || API_BASE}/api${path}`, {
    method: "POST",
    headers: AUTH_HEADERS,
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`POST ${path} ${res.status}: ${text.substring(0, 200)}`);
  }
  return res.json();
}

async function callAI(prompt) {
  const provider = opts.aiProvider;
  const key = opts.aiKey || process.env.AI_API_KEY;
  if (!key) throw new Error("AI API key required. Set --ai-key or AI_API_KEY env");

  const baseUrl = opts.aiBaseUrl || "https://api.openai.com/v1";
  const headers = { Authorization: `Bearer ${key}`, "Content-Type": "application/json" };

  // Support OpenAI, Anthropic, and custom providers
  const isAnthropic = provider === "anthropic";
  const body = isAnthropic
    ? { model: opts.aiModel, max_tokens: 8192, messages: [{ role: "user", content: prompt }], stream: true }
    : { model: opts.aiModel, messages: [{ role: "user", content: prompt }], max_tokens: 8192, stream: true };

  const endpoint = isAnthropic ? `${baseUrl}/messages` : `${baseUrl}/chat/completions`;
  const response = await fetch(endpoint, { method: "POST", headers, body: JSON.stringify(body) });
  if (!response.ok) {
    const err = await response.text();
    throw new Error(`AI API error: ${err.substring(0, 300)}`);
  }

  let fullResponse = "";
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    const lines = buffer.split("\n");
    buffer = lines.pop() || "";

    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed || !trimmed.startsWith("data: ")) continue;
      const data = trimmed.slice(6);
      if (data === "[DONE]") break;

      try {
        const parsed = JSON.parse(data);
        let content = "";
        if (isAnthropic) {
          content = parsed.delta?.text || parsed.content_block?.text || "";
        } else {
          content = parsed.choices?.[0]?.delta?.content || "";
        }
        if (content) {
          fullResponse += content;
          process.stdout.write(content);
        }
      } catch { /* skip parse errors */ }
    }
  }

  return fullResponse;
}

async function reportPhaseComplete(versionId, phaseKey, output, status = "done") {
  await apiPost("/webhooks/phase-complete", { version_id: versionId, phase_key: phaseKey, output, status });
}

async function fetchContext(versionId, projectId) {
  try {
    const version = await apiGet(`/versions/${versionId}`);
    const project = version.project || (projectId ? await apiGet(`/projects/${projectId}`) : null);
    const ctx = {};

    if (version.analysis) ctx.analysis = version.analysis.substring(0, 2000);
    if (version.prd) ctx.prd = version.prd.substring(0, 2000);
    if (version.architecture) ctx.architecture = version.architecture.substring(0, 3000);
    if (version.erd) {
      const erd = typeof version.erd === 'string' ? JSON.parse(version.erd) : version.erd;
      ctx.erdTables = erd.nodes?.map(n => `${n.label}(${(n.fields || []).join(',')})`).join('\n') || '';
      ctx.erdEdges = erd.edges?.map(e => `${e.from} -> ${e.to} (${e.relation})`).join('\n') || '';
    }
    if (version.api_contract) {
      const api = typeof version.api_contract === 'string' ? JSON.parse(version.api_contract) : version.api_contract;
      ctx.apiContract = api.map(e => `${e.method} ${e.path}`).join('\n') || '';
    }
    if (version.standards) ctx.standards = version.standards.substring(0, 2000);
    if (version.agents) ctx.agents = version.agents.substring(0, 2000);

    ctx.projectIdea = project?.idea || '';
    ctx.target = project?.target || 'web';
    ctx.stack = project?.stack || '';

    return ctx;
  } catch {
    return {};
  }
}

function buildEnhancedPrompt(phasePrompt, ctx) {
  let context = '\n\n=== CONTEXT FROM PIPELINE ===\n';
  if (ctx.analysis) context += `\n## Analisa\n${ctx.analysis}\n`;
  if (ctx.prd) context += `\n## PRD\n${ctx.prd}\n`;
  if (ctx.architecture) context += `\n## Arsitektur\n${ctx.architecture}\n`;
  if (ctx.erdTables) context += `\n## Database Tables\n${ctx.erdTables}\n`;
  if (ctx.erdEdges) context += `\n## Relations\n${ctx.erdEdges}\n`;
  if (ctx.apiContract) context += `\n## API Endpoints\n${ctx.apiContract}\n`;
  if (ctx.standards) context += `\n## STANDARDS.md\n${ctx.standards}\n`;
  if (ctx.agents) context += `\n## AGENTS.md\n${ctx.agents}\n`;

  return `${phasePrompt}${context}\n\nIkuti STANDARDS.md untuk coding convention. Baca AGENTS.md untuk aturan perilaku AI agent.`;
}

async function main() {
  console.log(chalk.bold("\n🧠 AI Planning Studio — Phase Executor\n"));
  console.log(chalk.dim(`API: ${opts.api || API_BASE}`));
  console.log(chalk.dim(`Project ID: ${opts.projectId || "auto"}`));
  console.log(chalk.dim(`AI Provider: ${opts.aiProvider} (${opts.aiModel})\n`));

  // Fetch project + phases
  let projectId = opts.projectId;
  let versionId = opts.versionId;

  // If we have projectId but no versionId, find latest version
  if (projectId && !versionId) {
    const project = await apiGet(`/projects/${projectId}`);
    const latestToken = await apiGet(`/projects/${projectId}/tokens`);
    // First version
    const versions = project.versions;
    if (versions?.length > 0) {
      versionId = versions[0].id;
    }
  }

  // Validate
  if (!versionId && !projectId) {
    console.error(chalk.red("ERROR: --version-id or --project-id required"));
    process.exit(1);
  }

  // If only projectId, find latest version
  if (projectId && !versionId) {
    const project = await apiGet(`/projects/${projectId}`);
    if (project.versions?.length > 0) {
      versionId = project.versions[0].id;
    }
  }

  if (!versionId) {
    console.error(chalk.red("ERROR: No version found"));
    process.exit(1);
  }

  console.log(chalk.dim(`Version ID: ${versionId}\n`));

  // Latest: fetch direct version detail
  const version = await apiGet(`/versions/${versionId}`);
  const phases = version.phases || [];

  if (phases.length === 0) {
    console.error(chalk.red("ERROR: No phases found in this version. Run the pipeline first."));
    process.exit(1);
  }

  // Fetch current progress
  const projectData = await apiGet(`/projects/${version.project_id}`);
  let currentProgress = [];

  // Determine start index
  const startFrom = opts.from || null;
  let startIdx = 0;
  if (startFrom) {
    startIdx = phases.findIndex((p) => p.key === startFrom);
    if (startIdx < 0) {
      console.error(chalk.red(`ERROR: Phase key "${startFrom}" not found`));
      process.exit(1);
    }
  }

  // Execute phases
  const totalPhases = phases.length;
  let completed = startIdx;

  for (let i = startIdx; i < totalPhases; i++) {
    const phase = phases[i];
    const spinner = ora({
      text: `${formatPhaseTitle(phase)}`,
      spinner: "dots",
    }).start();

    try {
      // Fetch fresh context from pipeline before each phase
      const ctx = await fetchContext(versionId, projectId);
      const enhancedPrompt = buildEnhancedPrompt(phase.prompt, ctx);

      // Call AI
      const output = await callAI(enhancedPrompt);

      spinner.succeed(chalk.green(`✅ ${phase.title}`));

      // Report completion
      await reportPhaseComplete(versionId, phase.key, output);
      completed++;

      // Show progress
      console.log(chalk.dim(drawProgressBar(completed, totalPhases)));

      // Separator between phases
      if (i < totalPhases - 1) {
        console.log(chalk.dim("\n" + "─".repeat(50) + "\n"));
      }
    } catch (err) {
      spinner.fail(chalk.red(`❌ ${phase.title}: ${err.message}`));
      await reportPhaseComplete(versionId, phase.key, err.message, "error").catch(() => {});
      if (!opts.auto) {
        console.log(chalk.yellow("\nPress Enter to continue or Ctrl+C to abort..."));
        await new Promise((resolve) => process.stdin.once("data", resolve));
      } else {
        // In auto mode, continue to next phase
        console.log(chalk.yellow("⚠️  Continuing to next phase...\n"));
      }
    }
  }

  console.log(chalk.bold.green(`\n🎉 All ${totalPhases} phases completed!`));
  console.log(chalk.dim(`\nVisit your project dashboard to see progress:`));
  console.log(chalk.cyan(`${opts.api || API_BASE}/projects/${projectId || version.project_id}`));
}

main().catch((err) => {
  console.error(chalk.red("\nFATAL:"), err);
  process.exit(1);
});
