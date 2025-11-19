# Laratickets API Documentation

## Authentication

All API endpoints require authentication using Laravel Sanctum:

```http
Authorization: Bearer {token}
```

## Base URL

```
/api/v1/laratickets
```

## Tickets

### List Tickets

```http
GET /tickets
```

**Query Parameters:**
- `status` (string): Filter by status
- `level` (integer): Filter by level (1-4)
- `department_id` (integer): Filter by department
- `open_only` (boolean): Show only open tickets
- `overdue_only` (boolean): Show only overdue tickets
- `per_page` (integer): Items per page (default: 15)

**Response:** `200 OK`

### Create Ticket

```http
POST /tickets
```

**Body:**
```json
{
  "subject": "string (required)",
  "description": "string (required)",
  "priority": "low|medium|high|critical (required)",
  "department_id": "integer (required)"
}
```

**Response:** `201 Created`

### Get Ticket

```http
GET /tickets/{id}
```

**Response:** `200 OK`

### Update Ticket

```http
PUT /tickets/{id}
```

**Body:**
```json
{
  "subject": "string (optional)",
  "description": "string (optional)",
  "status": "string (optional)",
  "priority": "string (optional)",
  "department_id": "integer (optional)"
}
```

**Response:** `200 OK`

### Delete Ticket

```http
DELETE /tickets/{id}
```

**Response:** `200 OK`

### Close Ticket

```http
POST /tickets/{id}/close
```

**Response:** `200 OK`

### Resolve Ticket

```http
POST /tickets/{id}/resolve
```

**Response:** `200 OK`

### Cancel Ticket

```http
POST /tickets/{id}/cancel
```

**Body:**
```json
{
  "reason": "string (optional)"
}
```

**Response:** `200 OK`

## Escalations

### Request Escalation

```http
POST /tickets/{id}/escalations
```

**Body:**
```json
{
  "target_level_id": "integer (required)",
  "justification": "string (required, min: 10)"
}
```

**Response:** `201 Created`

### Approve Escalation

```http
POST /escalations/{id}/approve
```

**Response:** `200 OK`

### Reject Escalation

```http
POST /escalations/{id}/reject
```

**Body:**
```json
{
  "reason": "string (required, min: 10)"
}
```

**Response:** `200 OK`

## Evaluations

### Evaluate Ticket

```http
POST /tickets/{id}/evaluations
```

**Body:**
```json
{
  "score": "number (required, 1.0-5.0)",
  "comment": "string (optional)"
}
```

**Response:** `201 Created`

### Get Evaluation Statistics

```http
GET /evaluations/statistics
```

**Response:** `200 OK`

## Risk Assessments

### Assess Ticket Risk

```http
POST /tickets/{id}/risk-assessments
```

**Body:**
```json
{
  "risk_level": "low|medium|high|critical (required)",
  "justification": "string (required, min: 10)"
}
```

**Response:** `201 Created`

### Get High Risk Tickets

```http
GET /risk-assessments/high-risk
```

**Response:** `200 OK`

### Get Risk Statistics

```http
GET /risk-assessments/statistics
```

**Response:** `200 OK`

## Response Format

### Success Response

```json
{
  "message": "Operation successful",
  "data": {
    // Resource data
  }
}
```

### Error Response

```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

## Status Codes

- `200 OK`: Successful request
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid input
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `422 Unprocessable Entity`: Validation failed
- `500 Internal Server Error`: Server error
