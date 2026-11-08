import { http } from './http';

export interface Summary {
  revenue: string;
  orders: number;
  pizzas_sold: number;
  average_order_value: string;
  average_pizzas_per_order: string;
}

export interface MonthRevenue {
  month: string;
  revenue: string;
  orders: number;
}

export interface HourOrders {
  hour: number;
  orders: number;
}

/** Orders on a day of the week, 1 for Monday to 7 for Sunday. */
export interface WeekdayOrders {
  weekday: number;
  orders: number;
}

export interface PizzaTypeSales {
  code: string;
  name: string;
  category: string;
  quantity: number;
  revenue: string;
}

export interface CategorySales {
  category: string;
  quantity: number;
  revenue: string;
}

/** The dates GET /statistics/* narrow to, both included; an empty one leaves that end open. */
export interface DateRange {
  ordered_from?: string;
  ordered_to?: string;
}

export interface Statistics {
  summary: Summary;
  revenueByMonth: MonthRevenue[];
  ordersByHour: HourOrders[];
  ordersByWeekday: WeekdayOrders[];
  pizzaTypeSales: PizzaTypeSales[];
  categorySales: CategorySales[];
}

async function fetchStatistic<T>(
  path: string,
  dateRange: DateRange,
  signal?: AbortSignal
): Promise<T> {
  const filter = Object.fromEntries(Object.entries(dateRange).filter(([, date]) => date));
  const { data } = await http.get<{ data: T }>(`/statistics/${path}`, {
    params: { filter },
    signal
  });

  return data.data;
}

export async function fetchStatistics(
  dateRange: DateRange,
  signal?: AbortSignal
): Promise<Statistics> {
  const [summary, revenueByMonth, ordersByHour, ordersByWeekday, pizzaTypeSales, categorySales] =
    await Promise.all([
      fetchStatistic<Summary>('summary', dateRange, signal),
      fetchStatistic<MonthRevenue[]>('revenue-by-month', dateRange, signal),
      fetchStatistic<HourOrders[]>('orders-by-hour', dateRange, signal),
      fetchStatistic<WeekdayOrders[]>('orders-by-weekday', dateRange, signal),
      fetchStatistic<PizzaTypeSales[]>('pizza-type-sales', dateRange, signal),
      fetchStatistic<CategorySales[]>('category-sales', dateRange, signal)
    ]);

  return { summary, revenueByMonth, ordersByHour, ordersByWeekday, pizzaTypeSales, categorySales };
}
