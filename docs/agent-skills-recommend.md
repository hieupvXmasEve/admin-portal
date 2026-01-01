# Project Agent Skills Catalog

This project includes a suite of custom **Claude Agent Skills** designed to enforce our **Modular Monolith** architecture and automate repetitive tasks.

> **How to use**: Simply ask Claude to perform the task (e.g., "Create an action to register students"). Claude will automatically detect and use the appropriate skill.

## 🏗 Scaffolding & Architecture

| Skill Name | Description | Trigger Example |
| :--- | :--- | :--- |
| **`scaffold-module`** | Creates a new Domain Module structure (`app/Modules/{Name}`). | "Scaffold a new Inventory module" |
| **`scaffold-crud`** | Orchestrates a full CRUD feature (Backend + Frontend). | "Scaffold CRUD for Events in Academic module" |
| **`create-contract`** | Generates a Shared Contract/Interface for cross-module communication. | "Create a contract for checking student GPA" |

## 🔧 Backend Components

| Skill Name | Description | Trigger Example |
| :--- | :--- | :--- |
| **`create-action`** | Generates a Business Action class (`run()` method). | "Create an action to update user profile" |
| **`create-query`** | Generates a Read-only Query class (`handle()` method). | "Create a query to list all active courses" |
| **`create-controller`** | Generates a thin Controller (Web or API). | "Create a controller for managing students" |
| **`create-request`** | Generates a FormRequest for validation. | "Create a request to validate course creation" |
| **`create-dto`** | Generates a Data Transfer Object. | "Create a DTO for student registration data" |
| **`generate-policy`** | Generates a Policy for authorization. | "Generate a policy for the Event model" |

## 🎨 Frontend Components

| Skill Name | Description | Trigger Example |
| :--- | :--- | :--- |
| **`create-inertia-page`** | Generates a Vue 3 + Inertia page (Index, Create, Edit). | "Create a student list page" |

## ✅ Quality & Security

| Skill Name | Description | Trigger Example |
| :--- | :--- | :--- |
| **`create-test`** | Generates Pest/PHPUnit tests. | "Create a unit test for the RegisterAction" |
| **`review-architecture`** | Checks for Modular Monolith violations (e.g., cross-module imports). | "Review this code for architecture violations" |
| **`review-security-rules`** | Audits code for Gate/Policy authorization security gaps. | "Check the security of these routes" |

---

## Installation

These skills are located in `.claude/skills/`. They should be committed to the repository so all team members using Claude Code have access to them.
