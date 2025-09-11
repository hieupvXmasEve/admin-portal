<script setup lang="ts">
import EnrollmentGrowth from '@/components/dashboard/EnrollmentGrowth.vue';
import StudentDistribution from '@/components/dashboard/StudentDistribution.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2"> 📊 Academic Analytics Dashboard </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-6">
                <StudentDistribution />
                <EnrollmentGrowth />
                <!-- <AcademicStanding /> -->
                <!-- <GraduationRate /> -->
            </div>
        </CardContent>
    </Card>
</template>
