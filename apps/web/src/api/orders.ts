import { http } from './http';

export interface PizzaType {
  code: string;
  name: string;
  category: string;
  pizzas?: Pizza[];
  ingredients?: string[];
}

export interface Pizza {
  code: string;
  size: string;
  price: string;
  pizza_type?: PizzaType;
}

export interface OrderItem {
  id: number;
  quantity: number;
  pizza?: Pizza;
  line_total?: string;
}

export interface Order {
  id: number;
  ordered_at: string;
  pizza_count: number;
  total: string;
  order_items?: OrderItem[];
}

export interface Page<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
}

/**
 * The filters GET /orders takes, each sent as filter[name]; an empty one is left out.
 */
export interface OrderFilters {
  search?: string;
  ordered_from?: string;
  ordered_to?: string;
  category?: string;
  size?: string;
  pizza_type?: string;
}

const ORDER_LINES_INCLUDE = 'orderItems.pizza.pizzaType';

export async function fetchOrders(
  filters: OrderFilters,
  sort: string,
  page: number,
  signal?: AbortSignal
): Promise<Page<Order>> {
  const filter = Object.fromEntries(Object.entries(filters).filter(([, value]) => value));
  const { data } = await http.get<Page<Order>>('/orders', {
    params: { filter, sort, page, include: ORDER_LINES_INCLUDE },
    signal
  });

  return data;
}

export async function fetchOrder(orderId: number): Promise<Order> {
  const { data } = await http.get<{ data: Order }>(`/orders/${orderId}`, {
    params: { include: ORDER_LINES_INCLUDE }
  });

  return data.data;
}

export async function fetchPizzaTypes(): Promise<PizzaType[]> {
  const { data } = await http.get<{ data: PizzaType[] }>('/pizza-types');

  return data.data;
}
