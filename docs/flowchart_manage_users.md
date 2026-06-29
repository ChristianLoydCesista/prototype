# Manage Users Flowchart (Super Admin)

## **Entry Point**

```
Admin Login → dashboard.php → Sidebar "Users" → app/admin/super_admin/users.php
  ↓ (Super Admin Check)
❌ Access Denied (non-super)
  ↓ (✅ Super Admin)
Users List + Create Form
```

## **Main Flow** (mermaid diagram)

```mermaid
graph TD
  A[Super Admin Login] --> B[dashboard.php Sidebar Click]
  B --> C[Load users.php]
  C --> D[Check role == super_admin]
  D -->|No| E[Die
```
