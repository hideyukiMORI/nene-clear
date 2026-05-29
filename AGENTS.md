# Agent / AI Guide

Entry point for AI agents working on **NeNe Clear** (private repo `nene-clear`).

## Read First

- **Portfolio strategy (external):** [publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md)
- **Philosophy:** `docs/explanation/philosophy.md`
- **Product vision:** `docs/explanation/product-vision.md`
- **Expansion roadmap (1–5):** `docs/explanation/expansion-roadmap.md`
- **Requirements:** `docs/explanation/requirements.md`
- **Terminology (binding):** `docs/explanation/terminology.md`
- **Compliance (binding):** `docs/explanation/accounting-compliance.md`
- **Current work:** `docs/todo/current.md`
- **Roadmap:** `docs/roadmap.md`

## Operating Rules

- Issue-driven; no direct commits to `main`
- Do **not** edit `nene-invoice` for Clear product work — this repo is canonical
- Strategy changes → update `publication-strategy` first, then product docs here
- Namespace: `NeneClear\`; money: integer cents

## Framework

[NENE2](https://github.com/hideyukiMORI/NENE2) via Composer when runtime lands.
