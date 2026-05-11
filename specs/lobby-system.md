# Lobby System Specification

## Responsibilities

- create room
- generate room code
- generate QR code
- join room
- manage players
- manage host
- manage ready state

---

## Requirements

### Create Room

Host can:

- choose narration mode
- define player limit
- configure timers
- configure role setup

---

### Join Room

Players join via:

- QR code
- room code

No account required initially.

---

### Lobby Features

- player list
- ready state
- host controls
- remove player
- start game validation

---

## Validation Rules

- player count must match role count
- minimum players required
- no duplicate nicknames in same room
