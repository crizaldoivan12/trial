<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>{{ $title ?? 'Department Report' }}</title>
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
      td.num {
        text-align: right;
      }
    </style>
  </head>
  <body>
    <div class="header">
      <p class="title">{{ $title ?? 'Department Report' }}</p>
      <p class="meta">
        Date generated: {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}
      </p>
    </div>

    <table>
      <thead>
        <tr>
          <th>Department</th>
          <th>Total Requests</th>
          <th>Total Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach(($rows ?? []) as $row)
          <tr>
            <td>{{ $row->department_name ?? '—' }}</td>
            <td class="num">{{ $row->total_amount ?? 0 }}</td>
            <td class="num">
              @php
                $amt = $row->sum_amount ?? 0;
              @endphp
              ₱{{ number_format((float) $amt, 2, '.', ',') }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </body>
</html>

