# Game Engine Specification

## Responsibilities

- maintain game state
- process phase transitions
- resolve actions
- determine win conditions

---

## Core Phases

### Waiting

Players joining.

### Night

Roles perform actions.

### Day

Discussion phase.

### Voting

Players vote.

### Finished

Game over.

---

## Win Conditions

### Village Wins

All werewolves eliminated.

### Werewolves Win

Werewolves reach parity.

---

## Architecture Rules

Engine must remain independent from UI.
