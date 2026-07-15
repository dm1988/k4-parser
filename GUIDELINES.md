# Project Guidelines

## Project Overview

K4 Parser is a Laravel application for parsing crew roster and schedule documents, reviewing fleet activity, and exporting parsed events as iCalendar data.

The project is focused on automating the handling of crew scheduling and fleet activity information from documents into structured, reviewable data.

## Current Feature Focus

A current feature task is to add an airport info popover for each airport code in the flight route visualization.

### Goal
- Keep the flight route UI visually clean

### Design Direction
- The airport code remains the primary visual element

## Tech Stack

- Laravel
- PHP
- Tailwind CSS
- Vite
- MySQL/SQLite support
- Docker support via Laravel Sail

## Project Notes

- The app can run locally with SQLite or in Docker with MySQL and Redis.
- Frontend assets are built with Vite and Tailwind.
- OCR and document parsing are part of the core workflow.
- The project already includes export functionality for calendar-style event output.

## Working Notes

- Keep UI interactions subtle and intentional.
- Preserve the existing brand palette and visual language where possible.
- Prioritize clarity and minimal clutter in the route visualization.
- Use small, lightweight affordances for discoverability rather than heavy visual decoration.

## Color Palette

- #1B365D — Primary: main brand color for headers, backgrounds, and primary buttons
- #C5A059 — Accent: used for call-to-action buttons, highlights, and icons
- #F8F9FA — Neutral: used for light background space and supporting surfaces
- #0B0E14 — Depth: used for high-contrast text, footers, and strong borders
- #4A5568 — Tertiary: used for secondary text, sub-headlines, and subtle dividers
