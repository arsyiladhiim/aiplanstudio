// Minimal class-variance-authority — cukup untuk kebutuhan UI kit.
// ponytail: fitur subset (variants + defaults + compound tak didukung). Upgrade ke `cva` npm bila perlu.
type Vals = Record<string, Record<string, string>>;
type Config<V extends Vals> = {
  variants?: V;
  defaultVariants?: { [K in keyof V]?: keyof V[K] };
};
export type VariantProps<F> = F extends (props: infer P) => string ? Omit<P, "className"> : never;

export function cx(...parts: (string | false | null | undefined)[]) {
  return parts.filter(Boolean).join(" ");
}

export function cva<V extends Vals>(base: string, config?: Config<V>) {
  return (props?: Record<string, string | undefined> & { className?: string }) => {
    const classes = [base];
    if (config?.variants) {
      for (const key in config.variants) {
        const picked = props?.[key] ?? (config.defaultVariants?.[key] as string | undefined);
        if (picked != null && config.variants[key][picked]) classes.push(config.variants[key][picked]);
      }
    }
    if (props?.className) classes.push(props.className);
    return classes.filter(Boolean).join(" ");
  };
}
