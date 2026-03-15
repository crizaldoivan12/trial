<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>{{ $title ?? 'Summary Report' }}</title>
    <style>
      body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10px;
        color: #111827;
      }
      .header {
        margin-bottom: 12px;
      }
      .title {
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 4px 0;
      }
      .meta {
        font-size: 10px;
        color: #374151;
        margin: 0;
      }
      .cards {
        display: table;
        width: 100%;
        margin: 10px 0 12px 0;
      }
      .card {
        display: table-cell;
        padding: 8px;
        border: 1px solid #E5E7EB;
      }
      .card-title {
        font-size: 10px;
        color: #6B7280;
        text-transform: uppercase;
      }
      .card-value {
        font-size: 18px;
        font-weight: 700;
        color: #111827;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
      }
      th, td {
        border: 1px solid #E5E7EB;
        padding: 5px 6px;
      }
      th {
        background: #F3F4F6;
        font-weight: 700;
        text-align: left;
      }
    </style>
  </head>
  <body>
    <div class="header">
      <p class="title">{{ $title ?? 'Summary Report' }}</p>
      <p class="meta">
        Date generated: {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}
      </p>
      @if(!empty($filters['date_from']) || !empty($filters['date_to']))
        <p class="meta">
          Period:
          {{ $filters['date_from'] ?? 'Any' }}
          –
          {{ $filters['date_to'] ?? 'Any' }}
        </p>
      @endif
    </div>

    @php
      $summary = $summary ?? [];
    @endphp

    <div class="cards">
      <div class="card">
        <div class="card-title">Total Requests</div>
        <div class="card-value">{{ $summary['total_requests'] ?? 0 }}</div>
      </div>
      <div class="card">
        <div class="card-title">In Progress</div>
        <div class="card-value">{{ $summary['in_progress'] ?? 0 }}</div>
      </div>
      <div class="card">
        <div class="card-title">Completed</div>
        <div class="card-value">{{ $summary['completed'] ?? 0 }}</div>
      </div>
      <div class="card">
        <div class="card-title">On Hold</div>
        <div class="card-value">{{ $summary['on_hold'] ?? 0 }}</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Status Group</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach(($charts['by_status_groups'] ?? []) as $row)
          <tr>
            <td>{{ $row['status_group'] ?? '' }}</td>
            <td>{{ $row['total'] ?? 0 }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </body>
</html>

