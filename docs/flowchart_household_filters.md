flowchart TD
A[Load household.php] --> B{Super Admin?}

    B -->|No| C[Use admin_barangay_id]
    B -->|Yes| D{?barangay_id in GET?}
    D -->|Yes| E[selected_barangay_id = GET.barangay_id<br/>Fetch barangay]
    D -->|No| F[viewing_all_barangays = true]

    C --> G[where_clauses = 'h.barangay_id = ?'<br/>params = admin_barangay_id<br/>types = 'i']
    E --> G
    F --> H[No barangay WHERE clause]

    G --> I[Build other filters:<br/>Search, Risk, 4Ps → where_clauses[]]
    H --> I

    I --> J[where_sql = 'WHERE ' + implode AND]
    J --> K[COUNT(*) total_records<br/>bind_param + execute]

    K --> L[Paginated SELECT h.*, b.name<br/>JOIN barangays<br/>WHERE + LIMIT/OFFSET]
    L --> M[Display filtered households table]

    M --> N[Sidebar Dropdown onchange=applyFilter<br/>URL.set('barangay_id', value)<br/>page=1 → Reload]

    style A fill:#e1f5fe
    style N fill:#c8e6c9

```

**Household.php Barangay Filter Flow**

**Key Logic**:
```

if (!$is_super_admin) {
  $selected_barangay_id = $_SESSION['barangay_id']
} else if ($\_GET['barangay_id']) {
$selected_barangay_id = intval($\_GET['barangay_id'])
} else {
$viewing_all_barangays = true
}

if ($selected_barangay_id) {
$where_clauses[] = "h.barangay_id = ?";
$params[] = $selected_barangay_id;
$types .= 'i';
}

````

**JS Filter** (super admin sidebar):
```js
function applyFilter('barangay', value) {
  url.searchParams.set('barangay_id', value)
  url.searchParams.set('page', '1')
  window.location.href = url
}
````

**Result**: Secure (intval), paginated, super admin only, resets page=1.
