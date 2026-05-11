# Checkpoint — Loup-Garou Companion Platform

**Date:** 2026-05-11
**Status:** Active Development

---

## Completed

### Backend (Laravel)
- ✅ GameEngine service with all core logic
- ✅ GameController, NarratorController, VoteController
- ✅ Database migrations for games, players, actions, votes, events, snapshots
- ✅ RoleSeeder with Werewolf, Seer, Witch, Villager roles
- ✅ Routes for all game operations

### Frontend (Inertia/React)
- ✅ Games index page with join/create
- ✅ Games create page with narration mode selection
- ✅ Games show page with all views (lobby, player, narrator, finished)
- ✅ Role reveal card component
- ✅ Night action panels (Werewolf, Seer, Witch)
- ✅ Voting UI with confirmation
- ✅ Countdown timer
- ✅ Phase badge

### Specs
- ✅ All 10 spec files in place (lobby, role, game-engine, voting, narration, action, ui-ux, multiplayer, audio-visual, roadmap)

---

## In Progress

### Story Flow System — COMPLETED ✅
Added proper narration flow where narrator calls each role one at a time.

**Changes:**
1. **Database:** Added `active_role` column to games table
2. **Backend:** Added controller methods `callWerewolves`, `callSeer`, `callWitch`, `concludeNight`
3. **Frontend:** Narrator dashboard shows "Story Flow" card with buttons to call each role
4. **Player View:** Players only see their action panel when `active_role === their_role`
5. **Werewolf Pack Voting:** When multiple werewolves exist, they see vote counts and must agree on ONE victim
6. **Witch Skip Fix:** Each potion now has its own "Use" / "Skip" buttons instead of shared skip

---

## Remaining Tasks

### Phase 1: Auto-Refresh Fix (IMMEDIATE)
- [ ] Fix game show page to auto-refresh on state changes
- [ ] Ensure narrator actions trigger page reload
- [ ] Ensure player actions trigger page reload

### Phase 2: UX Polish (NEXT)
- [ ] Better loading states
- [ ] Flash messages for action confirmations
- [ ] Toast notifications for phase changes

### Phase 3: Game Flow Validation
- [ ] Validate role count matches player count on start
- [ ] Prevent starting with less than 4 players
- [ ] Prevent starting with no werewolves

---

## Technical Notes

### Key Files
- `resources/js/pages/games/show.tsx` — Main game view (needs auto-refresh fix)
- `app/Services/GameEngine.php` — Core game logic
- `app/Http/Controllers/Games/NarratorController.php` — Narrator actions
- `app/Http/Controllers/Games/VoteController.php` — Voting

### Current Heartbeat Logic (show.tsx:952-970)
```tsx
useEffect(() => {
    if (game.status !== 'playing' || game.mode !== 'auto_narrator') return;

    const interval = setInterval(async () => {
        const resp = await fetch(heartbeat(game.id).url, { method: 'POST' });
        const data = await resp.json();
        if (data.ticked || data.phase !== game.current_phase || data.status !== game.status) {
            router.reload({ only: ['game'] });
        }
    }, 3000);

    return () => clearInterval(interval);
}, [game.id, game.status, game.mode, game.current_phase]);
```

### Issue: `router.reload()` may not trigger proper Inertia page refresh when using data from `usePage()` or props.

### Fix Approach
Use Inertia's `usePage` hook with `poll` option or explicitly call the route and use Inertia's visit for proper page reload.

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Backend                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │
│  │GameController│  │NarratorCtrl │  │  VoteController  │   │
│  └──────┬──────┘  └──────┬──────┘  └────────┬────────┘   │
│         │                 │                    │             │
│  ┌──────┴────────────────┴────────────────────┴──────┐     │
│  │               GameEngine Service                  │     │
│  │  - startGame() - assignRoles() - changePhase()  │     │
│  │  - advanceToDay() - processNightActions()       │     │
│  │  - processVotes() - checkWinCondition()        │     │
│  └────────────────────────┬──────────────────────────┘     │
│                           │                                 │
│  ┌────────────────────────┴──────────────────────────┐     │
│  │              Models (Eloquent)                    │     │
│  │  Game, GamePlayer, GameAction, Vote, Role, etc. │     │
│  └─────────────────────────────────────────────────┘     │
└─────────────────────────────┬──────────────────────────────┘
                              │ Inertia
┌─────────────────────────────┴──────────────────────────────┐
│                    React Frontend                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │
│  │ games/index │  │games/create │  │  games/show     │   │
│  └─────────────┘  └─────────────┘  └────────┬────────┘   │
│                                              │             │
│  ┌───────────────────────────────────────────┴────────┐   │
│  │              Game Views (show.tsx)                  │   │
│  │  - WaitingLobby  - PlayerView  - NarratorDashboard│   │
│  │  - NightActionPanel  - Voting  - FinishedView     │   │
│  └───────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────┘
```
