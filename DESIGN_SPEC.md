# UI/UX Design Specification: NovelMangaReader (NMR)

This document serves as a creative and functional brief for generating a high-fidelity, modern UI/UX design for the NovelMangaReader (NMR) platform.

---

## 1. Project Vision
NovelMangaReader is a premium, high-speed platform for reading Manga, Manhwa, Webtoons, and Light Novels. The goal is to provide a "Netflix-like" immersive experience for readers, focusing on content discovery, readability, and community engagement.

## 2. Design Philosophy: "The Melt Aesthetic"
The project uses a custom design language called **Melt**.
- **Modern Minimalism**: Clean lines, generous whitespace, and focus on cover art.
- **Dark Mode First**: Deep charcoal backgrounds (#0f172a) with vibrant primary accents (#60a5fa).
- **Glassmorphism**: Subtle blurs and semi-transparent surfaces for overlays and cards.
- **Interactive Feedback**: Soft transitions, hover-lifts, and micro-animations.

**NOTE (2026-03-09)**: The public-facing site is currently in a **Minimal SSR Skeleton** phase, prioritizing raw data delivery and performance over decorative styling. The "Melt Aesthetic" described below remains the long-term target for the platform's visual identity, while the current implementation focuses on semantic HTML without site-wide stylesheets.

## 3. Key User Personas
1.  **The Binge Reader**: Needs a seamless, distraction-free "Zen mode" reader.
2.  **The Discoverer**: Relies on high-quality cover art, tags, and "Latest Update" grids.
3.  **The Community Member**: Active in comments, rating series, and following authors.

## 4. Visual Identity & System
- **Typography**: Sans-serif primary (Inter/Roboto). Bold headings, readable body text (18px for novels).
- **Color Palette**:
  - Background: `#0f172a` (Slate 900)
  - Surface: `#1e293b` (Slate 800)
  - Primary: `#60a5fa` (Blue 400)
  - Success: `#34d399` (Emerald 400)
- **Border Radius**: Large (`1rem` / `16px`) for cards and buttons to feel friendly and modern.

## 5. Screen-Specific Requirements

### A. Homepage (Discovery Hub)
- **Hero Section**: A featured "Rail" (horizontal slider) showing top-tier series with high-res backdrops.
- **Content Grids**: 2/3 aspect ratio cards. Should show Title, Rating, and Type (Manga/Novel).
- **Sticky Navigation**: Floating glassmorphism header with a search bar and user profile toggle.

### B. Content Detail (The "Landing Page" of a Series)
- **Dynamic Backdrop**: The background should blur and tint based on the series' cover image.
- **Quick Stats**: Floating pills for "Status", "Chapters", "Views", and "Rating".
- **Call to Action**: Large "Continue Reading" button that stands out.

### C. The Reader (The Core Experience)
- **Novel Mode**: Clean typography, adjustable font sizes, and "Night Mode" optimizations.
- **Manga Mode**: Edge-to-edge image rendering, "Fit to Width/Height" options, and smooth scroll.
- **Floating Controls**: Minimalist bottom bar for chapter navigation that hides on scroll.

### D. Social & Community
- **Comment Cards**: Nested discussions, upvote/downvote buttons, and user badges (VIP, Admin, Moderator).
- **User Profiles**: High-impact header with cover photos and reading statistics (Total chapters read, followed series).

## 6. Technical Constraints for Designer
- **Mobile-First**: Design must work perfectly on mobile browsers (PWA-ready).
- **Vanilla CSS Friendly**: Avoid complex heavy frameworks; focus on what can be achieved with CSS Grid and Flexbox.
- **Accessibility**: High contrast for text, large touch targets for buttons.

---
*Created for Gemini CLI - Project: NovelMangaReader*
