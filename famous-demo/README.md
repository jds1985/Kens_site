# Famous-inspired comparison demo

Static comparison of the preserved public homepage. It is not the live site, not a members application, and not a deployment.

Parent can later publish **only this folder** by setting a separate Vercel project’s Root Directory to `famous-demo`. Do not point the existing static project at the repository root. The existing `.vercelignore` still uploads `preview/` alone.

## What is design, and what is source

The opening line “All mystics speak the same language” is the comparison design Jim asked for. It is not a sentence from the preserved homepage. Psalm 133, “the unknown philosopher,” and the screenshot figures 360 and “1980 teachings” are not on that homepage, so they are not used here. “1980” appears only in the copyright notice: materials were created from 1980 to the present. “32” appears only as the published “thirty-two wonderful paths” and “32 Degrees.”

Curriculum, lodges, membership, members, Hebrew, Greek, copyright, and the Zero Degree follow the preserved page `public-current/qabbalah.institute/index.html`. The tree image is the existing file `TreeOfLifeThree.png` from that public snapshot, copied here so this folder can be deployed on its own. No Famous screenshots, compiled JavaScript, or backend connections are included.

## Members link

`demo-config.js` sets `membersUrl` to an empty string. The page then explains that the isolated members demo is not connected, and it does not link to `https://qabbalah.institute/members/`. A later URL is used only when it is http or https and the host is not `qabbalah.institute` or HostGator. This folder has no database and does not sign anyone in.

## Check

From the repository root, with Chrome installed:

```bash
node famous-demo/test_demo.mjs
```

The test checks the authoritative homepage hash, the nine section ids, the absent live-members link, desktop and 390px overflow, body text size, and keyboard focus.
