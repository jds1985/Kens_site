import { createHash } from "node:crypto";
import { createServer } from "node:http";
import { readFileSync } from "node:fs";
import { extname, join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const demo = join(root, "famous-demo");
const homePath = join(root, "public-current/qabbalah.institute/index.html");
const expectedHome = "d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d";
const ids = ["home", "curriculum", "lodges", "membership", "members", "hebrew", "greek", "copyright", "zerodegree"];
const types = { ".html": "text/html", ".css": "text/css", ".js": "text/javascript", ".png": "image/png", ".mjs": "text/javascript" };

function fail(message) {
  console.error("FAIL:", message);
  process.exit(1);
}

const homeBytes = readFileSync(homePath);
if (createHash("sha256").update(homeBytes).digest("hex") !== expectedHome || homeBytes.length !== 83168) {
  fail("authoritative homepage hash changed");
}

const html = readFileSync(join(demo, "index.html"), "utf8");
const css = readFileSync(join(demo, "styles.css"), "utf8");
for (const id of ids) {
  if (!html.includes(`id="${id}"`) && !html.includes(`id='${id}'`)) fail(`missing #${id}`);
}
if (html.includes("qabbalah.institute/members") || html.includes("360") || /1980 teachings/i.test(html)) {
  fail("demo contains a live members link or an unverified statistic");
}
if (!html.includes("Comparison demo") || !css.includes("font-size: 1.2rem")) fail("demo banner or body size missing");

const server = createServer((req, res) => {
  const url = new URL(req.url, "http://127.0.0.1");
  const file = join(demo, decodeURIComponent(url.pathname === "/" ? "/index.html" : url.pathname));
  if (!file.startsWith(demo)) {
    res.writeHead(403);
    res.end();
    return;
  }
  try {
    const body = readFileSync(file);
    res.writeHead(200, { "content-type": types[extname(file)] || "application/octet-stream" });
    res.end(body);
  } catch {
    res.writeHead(404);
    res.end("missing");
  }
});

await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const port = server.address().port;
const chrome = spawn("/usr/local/bin/google-chrome", [
  "--headless=new",
  "--no-sandbox",
  "--disable-dev-shm-usage",
  "--remote-debugging-port=9223",
  "--user-data-dir=/tmp/famous-demo-chrome",
  "about:blank"
], { stdio: "ignore" });

function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }

let version;
for (let i = 0; i < 40; i++) {
  try {
    version = await (await fetch("http://127.0.0.1:9223/json/version")).json();
    break;
  } catch {
    await sleep(150);
  }
}
if (!version) fail("Chrome did not start");

const socket = new WebSocket(version.webSocketDebuggerUrl);
let seq = 0;
const pending = new Map();
socket.addEventListener("message", (event) => {
  const msg = JSON.parse(event.data);
  if (msg.id && pending.has(msg.id)) pending.get(msg.id)(msg);
});
await new Promise((resolve) => socket.addEventListener("open", resolve, { once: true }));

function send(method, params = {}, sessionId) {
  const id = ++seq;
  const payload = { id, method, params };
  if (sessionId) payload.sessionId = sessionId;
  socket.send(JSON.stringify(payload));
  return new Promise((resolve) => pending.set(id, resolve));
}

const target = await send("Target.createTarget", { url: `http://127.0.0.1:${port}/` });
const sessionId = (await send("Target.attachToTarget", { targetId: target.result.targetId, flatten: true })).result.sessionId;
await send("Page.enable", {}, sessionId);
await send("Runtime.enable", {}, sessionId);

async function evaluate(expression) {
  const reply = await send("Runtime.evaluate", { expression, returnByValue: true, awaitPromise: true }, sessionId);
  if (reply.result.exceptionDetails) fail(JSON.stringify(reply.result.exceptionDetails));
  return reply.result.result.value;
}

async function resize(width, height) {
  await send("Emulation.setDeviceMetricsOverride", { width, height, deviceScaleFactor: 1, mobile: width < 500 }, sessionId);
  await send("Page.reload", {}, sessionId);
  await sleep(500);
}

async function shot(name) {
  const data = (await send("Page.captureScreenshot", { format: "png" }, sessionId)).result.data;
  const path = `/opt/cursor/artifacts/${name}.png`;
  const { writeFileSync } = await import("node:fs");
  writeFileSync(path, Buffer.from(data, "base64"));
  return path;
}

async function measure() {
  return evaluate(`(() => {
    const body = getComputedStyle(document.body);
    const size = parseFloat(body.fontSize);
    const overflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    const note = document.querySelector("[data-members-note]");
    const live = [...document.querySelectorAll("a")].some((a) => (a.href || "").includes("qabbalah.institute/members"));
    return { size, overflow, note: note && !note.hidden, live };
  })()`);
}

await resize(1440, 1100);
await shot("famous-demo-desktop");
const desktop = await measure();
if (desktop.size < 18 || desktop.overflow > 1 || !desktop.note || desktop.live) fail("desktop layout " + JSON.stringify(desktop));

await resize(390, 1200);
await shot("famous-demo-mobile");
const closed = await measure();
if (closed.overflow > 1) fail("mobile overflow " + closed.overflow);

await evaluate(`document.querySelector("[data-menu-button]").focus(); document.querySelector("[data-menu-button]").click();`);
const opened = await measure();
const focus = await evaluate(`(() => {
  const button = document.querySelector("[data-menu-button]");
  button.focus();
  return { expanded: button.getAttribute("aria-expanded"), active: document.activeElement === button };
})()`);
if (opened.overflow > 1) fail("open menu overflow");
if (focus.expanded !== "true" || !focus.active) fail("menu keyboard state " + JSON.stringify(focus));
await evaluate(`document.querySelector("#site-nav a").focus()`);
const linkFocused = await evaluate(`document.activeElement && document.activeElement.matches("#site-nav a")`);
if (!linkFocused) fail("section link did not take focus");
await shot("famous-demo-mobile-menu", 390, 1200);

await send("Emulation.setDeviceMetricsOverride", { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
await evaluate(`location.hash = "#zerodegree"`);
await sleep(200);
const tall = await measure();
if (tall.overflow > 1) fail("tall page overflow");

chrome.kill("SIGKILL");
server.close();
console.log("ok famous demo", JSON.stringify({ desktop, closed, focus }));
