import { DataTable, schema } from '@/components/data-table';
import { z } from 'zod';

const data: z.infer<typeof schema>[] = [];

const YourMatches = () => {
  return (
    <div>
      <DataTable data={data} />
    </div>
  );
};

export default YourMatches;
