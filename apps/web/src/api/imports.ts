import { http } from './http';

/**
 * The dataset's four files, each sent under the field POST /imports expects.
 */
export interface SalesDataFiles {
  pizza_types: File;
  pizzas: File;
  orders: File;
  order_details: File;
}

export interface ImportedRowCounts {
  pizza_types: number;
  ingredients: number;
  pizzas: number;
  orders: number;
  order_items: number;
}

export async function importSalesData(files: SalesDataFiles): Promise<ImportedRowCounts> {
  const formData = new FormData();

  for (const [field, file] of Object.entries(files)) {
    formData.append(field, file);
  }

  const { data } = await http.post<{ data: ImportedRowCounts }>('/imports', formData);

  return data.data;
}
