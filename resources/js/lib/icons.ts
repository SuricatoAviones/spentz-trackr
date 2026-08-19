import {
    Banknote,
    Bitcoin,
    Car,
    CreditCard,
    Gamepad2,
    GraduationCap,
    HeartPulse,
    Landmark,
    Receipt,
    Shirt,
    ShoppingCart,
    Smartphone,
    Tag,
    Utensils,
    Wallet,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const ICONS: Record<string, LucideIcon> = {
    'shopping-cart': ShoppingCart,
    utensils: Utensils,
    car: Car,
    zap: Zap,
    'heart-pulse': HeartPulse,
    'gamepad-2': Gamepad2,
    shirt: Shirt,
    'graduation-cap': GraduationCap,
    tag: Tag,
    bitcoin: Bitcoin,
    landmark: Landmark,
    wallet: Wallet,
    banknote: Banknote,
    'credit-card': CreditCard,
    smartphone: Smartphone,
    receipt: Receipt,
};

export function iconByName(name: string): LucideIcon {
    return ICONS[name] ?? Tag;
}

export {
    ShoppingCart,
    Utensils,
    Car,
    Zap,
    HeartPulse,
    Gamepad2,
    Shirt,
    GraduationCap,
    Tag,
    Bitcoin,
    Landmark,
    Wallet,
    Banknote,
    CreditCard,
    Smartphone,
    Receipt,
};
