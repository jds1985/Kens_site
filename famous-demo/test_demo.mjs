import { createHash } from "node:crypto";
import { createServer } from "node:http";
import { readFileSync, writeFileSync } from "node:fs";
import { extname, join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const demo = join(root, "famous-demo");
const homePath = join(root, "public-current/qabbalah.institute/index.html");
const expectedHome = "d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d";
const types = {
  ".html": "text/html", ".css": "text/css", ".js": "text/javascript",
  ".png": "image/png", ".jpg": "image/jpeg", ".webp": "image/webp", ".svg": "image/svg+xml"
};

function fail(message) {
  console.error("FAIL:", message);
  process.exit(1);
}

const homeBytes = readFileSync(homePath);
if (createHash("sha256").update(homeBytes).digest("hex") !== expectedHome) fail("homepage hash changed");
const page = readFileSync(join(demo, "index.html"), "utf8");
if (page.includes("famous.ai") || page.includes("supabase") || page.includes("qabbalah.institute/members")) {
  fail("page still points at a live backend or members host");
}
for (const id of ["philosophy", "teachings", "path", "library", "gematria", "lineage", "reflections"]) {
  if (!page.includes(`id="${id}"`)) fail("missing section " + id);
}
if (!page.includes("All mystics speak") || !page.includes("the same language")) fail("hero heading missing");

const server = createServer((req, res) => {
  const url = new URL(req.url, "http://127.0.0.1");
  const file = join(demo, decodeURIComponent(url.pathname === "/" ? "/index.html" : url.pathname));
  if (!file.startsWith(demo)) { res.writeHead(403); res.end(); return; }
  try {
    const body = readFileSync(file);
    res.writeHead(200, { "content-type": types[extname(file)] || "application/octet-stream" });
    res.end(body);
  } catch { res.writeHead(404); res.end("missing"); }
});
await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const port = server.address().port;
const chrome = spawn("/usr/local/bin/google-chrome", [
  "--headless=new", "--no-sandbox", "--disable-dev-shm-usage",
  "--remote-debugging-port=9333", "--user-data-dir=/tmp/famous-restore-chrome", "about:blank"
], { stdio: "ignore" });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let version;
for (let i = 0; i < 40; i++) {
  try { version = await (await fetch("http://127.0.0.1:9333/json/version")).json(); break; }
  catch { await sleep(150); }
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
await send("Network.enable", {}, sessionId);
async function evaluate(expression) {
  const reply = await send("Runtime.evaluate", { expression, returnByValue: true, awaitPromise: true }, sessionId);
  if (reply.result.exceptionDetails) fail(JSON.stringify(reply.result.exceptionDetails));
  return reply.result.result.value;
}
async function resize(width, height) {
  await send("Emulation.setDeviceMetricsOverride", { width, height, deviceScaleFactor: 1, mobile: width < 500 }, sessionId);
  await send("Page.reload", { ignoreCache: true }, sessionId);
  await sleep(900);
}
async function shot(name) {
  await evaluate(`document.documentElement.style.scrollBehavior = "auto"; window.scrollTo(0, 0); document.documentElement.scrollTop = 0; document.body.scrollTop = 0;`);
  await sleep(250);
  const data = (await send("Page.captureScreenshot", { format: "png", captureBeyondViewport: false }, sessionId)).result.data;
  const path = `/opt/cursor/artifacts/${name}.png`;
  writeFileSync(path, Buffer.from(data, "base64"));
}
async function check(width) {
  await evaluate(`(async () => {
    const imgs = [...document.images];
    for (const img of imgs) img.loading = "eager";
    document.documentElement.style.scrollBehavior = "auto";
    window.scrollTo(0, document.body.scrollHeight);
    await new Promise((r) => setTimeout(r, 400));
    window.scrollTo(0, 0);
    await Promise.all(imgs.map((img) => img.complete && img.naturalWidth ? null : new Promise((resolve) => {
      img.addEventListener("load", resolve, { once: true });
      img.addEventListener("error", resolve, { once: true });
    })));
  })()`);
  return evaluate(`(() => {
    const imgs = [...document.images];
    const broken = imgs.filter((img) => !img.complete || img.naturalWidth === 0).map((img) => img.getAttribute("src"));
    const overflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    const hero = document.querySelector("h1")?.innerText || "";
    return { width: ${width}, images: imgs.length, broken, overflow, hero, fonts: getComputedStyle(document.body).fontFamily };
  })()`);
}

await resize(1440, 900);
const desktop = await check(1440);
await shot("famous-restore-desktop");
await resize(390, 844);
const phone = await check(390);
await shot("famous-restore-phone");
const opened = await evaluate(`(() => {
  const button = document.querySelector('[aria-label="Toggle menu"]');
  button.focus();
  button.click();
  const menu = document.getElementById("demo-menu");
  const link = menu.querySelector("a");
  link.focus();
  return {
    open: menu.classList.contains("is-open"),
    focused: document.activeElement === link,
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth
  };
})()`);
await shot("famous-restore-phone-menu");
if (desktop.broken.length || phone.broken.length) fail("broken images " + JSON.stringify(desktop.broken.concat(phone.broken)));
if (desktop.overflow > 1 || phone.overflow > 1 || opened.overflow > 1) fail("overflow " + JSON.stringify({ desktop, phone, opened }));
if (!opened.open || !opened.focused) fail("menu " + JSON.stringify(opened));
if (!desktop.hero.includes("All mystics speak")) fail("hero missing");
if (desktop.images < 12) fail("too few images " + desktop.images);
console.log("ok restore", JSON.stringify({ desktop, phone, opened }));
socket.close();
chrome.kill("SIGKILL");
server.close();
process.exit(0);
