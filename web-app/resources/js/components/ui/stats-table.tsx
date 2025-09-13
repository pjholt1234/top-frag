interface TableColumn {
  header: string;
  width: string;
}

interface TableRow {
  label: string;
  values: Array<{
    value: string | number;
    className?: string;
    empty?: boolean;
  }>;
}

interface StatsTableProps {
  title: string;
  columns: TableColumn[];
  rows: TableRow[];
  className?: string;
}

export function StatsTable({ columns, rows, className = '' }: StatsTableProps) {
  return (
    <div
      className={`overflow-hidden rounded-lg border border-gray-700 ${className}`}
    >
      <table className="w-full table-fixed">
        <thead className="bg-gray-800/50">
          <tr>
            {columns.map((column, index) => (
              <th
                key={index}
                className={`${column.width} px-4 py-2 ${
                  index === 0 ? 'text-left' : 'text-center'
                } text-xs font-medium text-gray-400 uppercase tracking-wider`}
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-700">
          {rows.map((row, rowIndex) => (
            <tr key={rowIndex}>
              <td className="px-4 py-2 text-sm font-medium text-gray-300">
                {row.label}
              </td>
              {row.values.map((value, valueIndex) => (
                <td key={valueIndex} className="px-4 py-2 text-center">
                  {value.empty ? (
                    <span className="text-sm font-medium text-gray-400">
                      &nbsp;
                    </span>
                  ) : (
                    <span
                      className={`text-sm font-medium ${value.className || 'text-gray-300'}`}
                    >
                      {value.value}
                    </span>
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
