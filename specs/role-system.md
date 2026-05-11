# Role System Specification

## Core Principles

Roles must be:

- modular
- data-driven
- extensible

Avoid hardcoded behavior.

---

## MVP Roles

### Werewolf

Faction: Werewolves
Ability:

- choose night victim

---

### Seer

Faction: Village
Ability:

- inspect one player per night

---

### Witch

Faction: Village
Abilities:

- one save potion
- one kill potion

---

### Villager

Faction: Village
No special ability.

---

## Future Roles

Future support:

- Hunter
- Cupid
- Little Girl
- Bodyguard
- custom roles

---

## Role Structure

Each role contains:

- name
- faction
- description
- abilities
- night order
- win condition
