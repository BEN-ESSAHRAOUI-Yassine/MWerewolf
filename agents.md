# agents.md

## Project Name

Loup-Garou Companion Platform

A real-life social deduction companion application inspired by Les Loups-Garous de Thiercelieux.

---

# Core Vision

This project is NOT a traditional online multiplayer game.

The application is designed to support real-life social deduction sessions where players are physically together in the same place.

The app manages:

- hidden information
- role distribution
- narration
- game state
- voting
- actions
- timing
- immersion support

The human social experience remains the center of gameplay.

---

# Main Product Goals

## Primary Goal

Create immersive and memorable real-life social deduction experiences.

## Secondary Goal

Reduce logistical friction:

- role cards
- narration mistakes
- hidden information leaks
- tracking complexity

## Anti-Goals

The app must NOT become:

- a clone of Town of Salem
- an MMO/social network
- a screen-heavy experience
- an overly competitive ranked system
- a feature-overloaded RPG

---

# Core Principles

## Principle 1 — Human Interaction First

Players should spend more time:

- talking
- observing
- bluffing
- reacting

Than staring at screens.

---

## Principle 2 — Phones Are Support Tools

Phones are mainly used for:

- joining games
- viewing hidden role information
- night actions
- voting
- narration support

---

## Principle 3 — Narrator Is Valuable

The narrator is not a problem to eliminate.

The narrator creates:

- atmosphere
- pacing
- improvisation
- emotional tension

The app should support narrators instead of replacing them.

---

## Principle 4 — Modularity

Everything should be extensible:

- roles
- factions
- phases
- actions
- game modes

Avoid hardcoded role logic.

---

# Technical Stack

## Backend

- Laravel

## Frontend (MVP)

- Blade
- TailwindCSS
- Alpine.js

## Future Frontend

- Vue
- React
- PWA/mobile wrapper

---

# Core Architecture

The project must follow a modular architecture.

Recommended layers:

- Controllers
- Services
- Game Engine
- Database Layer
- Narration Mode Layer

Controllers must remain thin.

Business logic belongs inside services and engine classes.

---

# Core Systems

## Systems Overview

### Lobby System

Handles:

- room creation
- room joining
- player management

### Role System

Handles:

- role definitions
- role assignment
- abilities
- factions

### Game Engine

Handles:

- game state
- phase transitions
- win conditions
- action resolution

### Narration System

Handles:

- human narrator mode
- auto narrator mode
- pacing
- announcements

### Voting System

Handles:

- vote submission
- vote counting
- eliminations

### Action System

Handles:

- night actions
- role interactions
- action resolution order

---

# Narration Modes

## Human Narrator Mode

A real narrator controls pacing.

The app supports:

- hidden information
- tracking
- validations
- actions

The narrator controls:

- discussion duration
- atmosphere
- phase progression

---

## App Narrator Mode

The app controls:

- timers
- narration
- phase transitions
- action resolution

This mode should remain lightweight and social.

---

# Multiplayer Philosophy

Initial MVP focuses on:

- local network play
- same physical location
- QR code joining
- room code joining

Host device acts as authoritative server.

---

# UX Rules

## Rule 1

Minimal taps.

## Rule 2

Fast role interaction.

## Rule 3

Discussion should happen outside the screen.

## Rule 4

Dark atmospheric design.

## Rule 5

Immersion over complexity.

---

# Visual Direction

Themes:

- dark fantasy
- medieval village
- moonlight
- fog
- candles

Avoid:

- flashy arcade visuals
- cluttered interfaces
- childish UI

---

# Audio Direction

Use:

- subtle ambience
- suspense sounds
- atmospheric transitions

Avoid:

- loud arcade sounds
- comedic effects

---

# MVP Scope

## Included

- local multiplayer
- role assignment
- role reveal system
- narrator dashboard
- night actions
- voting
- win detection
- narrator modes

## Excluded Initially

- ranking systems
- monetization
- AI players
- cloud hosting
- cosmetics
- progression systems
- dozens of complex roles

---

# Folder Structure Recommendation

```text
app/
 ├── Game/
 │    ├── Engine/
 │    ├── Roles/
 │    ├── Actions/
 │    ├── Phases/
 │    ├── Narration/
 │    └── Services/
 │
 ├── Models/
 ├── Http/
 └── Events/

resources/
 ├── views/
 ├── js/
 └── css/

specs/
 ├── lobby-system.md
 ├── role-system.md
 ├── narration-system.md
 ├── game-engine.md
 ├── voting-system.md
 ├── action-system.md
 ├── ui-ux.md
 ├── multiplayer.md
 ├── audio-visual.md
 └── roadmap.md
```

---

# Development Philosophy

Prioritize:

- stable architecture
- clean systems
- immersive UX
- extensibility

Avoid premature optimization.

Build the MVP first.
