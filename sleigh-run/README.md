# Christmas Sleigh Run: Aaron Atkinson Realty

A branded, snake-style holiday game: steer the sleigh, collect house keys, and every key adds a present to your trail.
It's one self-contained `index.html` plus `logo.png`. There is no build step, and you can host it anywhere (Netlify, Vercel, GitHub Pages, your own website).

## Setup (edit `CONFIG` at the top of the `<script>` in index.html)

| Setting | What it does |
|---|---|
| `leadEndpoint` | URL that receives the end-screen email sign-up as a JSON POST (Formspree, Make/Zapier webhook, Google Apps Script). **The form stays hidden until you set this.** |
| `playEndpoint` | Optional URL pinged once per play, so you can count plays for the charity donation. |
| `phone`, `website` | Show "Call Aaron" / "See listings" buttons on the end screen. |
| `charity` | Charity wording, or `null` to remove it. |
| `roundSeconds`, `startStep`, `minStep`, `speedUp` | Length of a round and difficulty. |

## Compared with the original preview
- **Smooth motion**: the sleigh and presents glide between cells at full frame rate instead of jumping 5 times a second.
- **Faster input**: swipes register mid-swipe (not on finger lift), the d-pad fires on touch-down, turns pressed near a cell are taken right away, and up to 3 turns are buffered.
- **Forgiveness**: the first time you're about to crash, the sleigh hesitates one beat so a late turn can save you.
- **Juice**: sparkles, "+1" pops, sound effects (mutable), vibration on phones, snowfall, countdown ticks.
- **Landscape board on desktop**, portrait on phones, crisp on retina screens.
- **Honest numbers**: there's no hard-coded "amount raised", and the sign-up form really sends data (the original's form discarded it).
- Personal best, share button, and keyboard/screen-reader support.
